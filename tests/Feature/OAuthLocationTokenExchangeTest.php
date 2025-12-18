<?php

namespace Tests\Feature;

use App\Models\HLAccount;
use App\Services\HighLevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Test suite for OAuth Location Token Exchange fix
 *
 * This test verifies that the critical bug fix for token exchange works correctly:
 * - Only locationId is sent in the request (not companyId)
 * - Location ID extraction doesn't confuse company_id with location_id
 * - Error handling provides actionable feedback
 */
class OAuthLocationTokenExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected HighLevelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HighLevelService::class);
    }

    /**
     * Test that token exchange request only includes locationId parameter
     */
    public function test_token_exchange_request_only_includes_location_id()
    {
        // Create a test account with company token
        $account = HLAccount::factory()->create([
            'location_id' => 'test_location_123',
            'access_token' => 'company_token_xyz',
            'token_type' => 'Company',
            'company_id' => null, // Important: company_id not set initially
        ]);

        // Mock the HTTP client to verify request format
        Http::fake([
            'services.leadconnectorhq.com/oauth/locationToken' => Http::response([
                'access_token' => 'location_token_abc',
                'refresh_token' => 'refresh_token_def',
                'expires_in' => 86400,
                'userType' => 'Location',
                'locationId' => 'test_location_123',
                'companyId' => 'test_company_456',
            ], 200),
        ]);

        // Attempt token exchange
        $result = $this->service->exchangeCompanyTokenForLocation($account, 'test_location_123');

        // Verify the request was made with correct parameters
        Http::assertSent(function ($request) {
            // Verify endpoint
            if (!str_contains($request->url(), '/oauth/locationToken')) {
                return false;
            }

            // CRITICAL: Verify only locationId is sent (not companyId)
            $body = $request->body();
            parse_str($body, $params);

            // Should have locationId
            if (!isset($params['locationId'])) {
                return false;
            }

            // Should NOT have companyId
            if (isset($params['companyId'])) {
                return false; // This would cause "Location not found" error
            }

            // Verify Authorization header
            if (!$request->hasHeader('Authorization')) {
                return false;
            }

            return true;
        });

        // Verify result is successful
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals('location_token_abc', $result['access_token']);

        // Verify account was updated
        $account->refresh();
        $this->assertEquals('location_token_abc', $account->location_access_token);
        $this->assertEquals('Location', $account->token_type);
        $this->assertEquals('test_company_456', $account->company_id); // Now stored from response
    }

    /**
     * Test that location ID extraction doesn't use company_id
     */
    public function test_location_id_extraction_excludes_company_id()
    {
        // Simulate OAuth callback with both companyId and locationId
        $tokenResponse = [
            'access_token' => 'test_token',
            'companyId' => 'company_abc',
            'locationId' => 'location_xyz',
        ];

        // Create a mock request with query parameter
        $request = $this->createMock(\Illuminate\Http\Request::class);
        $request->method('has')->willReturn(false);
        $request->method('all')->willReturn([]);
        $request->method('session')->willReturnSelf();

        // Use reflection to call the protected method
        $controller = new \App\Http\Controllers\OAuthController(
            $this->service,
            app(\App\Logging\UserActionLogger::class)
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('extractLocationId');
        $method->setAccessible(true);

        // Extract location ID
        $locationId = $method->invoke($controller, $request, $tokenResponse);

        // CRITICAL: Should extract locationId, NOT companyId
        $this->assertEquals('location_xyz', $locationId);
        $this->assertNotEquals('company_abc', $locationId);
    }

    /**
     * Test error handling for 400 Bad Request
     */
    public function test_token_exchange_handles_400_error_gracefully()
    {
        $account = HLAccount::factory()->create([
            'location_id' => 'invalid_location',
            'access_token' => 'company_token',
            'token_type' => 'Company',
        ]);

        // Mock 400 error response
        Http::fake([
            'services.leadconnectorhq.com/oauth/locationToken' => Http::response([
                'message' => 'Location not found',
                'error' => 'Bad Request',
                'statusCode' => 400,
                'traceId' => 'test-trace-id',
            ], 400),
        ]);

        $result = $this->service->exchangeCompanyTokenForLocation($account, 'invalid_location');

        // Verify error is handled properly
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Unable to access the specified location', $result['error']);
        $this->assertEquals(400, $result['status']);
    }

    /**
     * Test that empty location_id throws validation error
     */
    public function test_token_exchange_requires_location_id()
    {
        $account = HLAccount::factory()->create([
            'access_token' => 'company_token',
            'token_type' => 'Company',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Location ID is required for token exchange');

        $this->service->exchangeCompanyTokenForLocation($account, '');
    }

    /**
     * Test that company_id is NOT required for token exchange
     */
    public function test_token_exchange_works_without_company_id()
    {
        // This is the critical fix - company_id should NOT be required
        $account = HLAccount::factory()->create([
            'location_id' => 'test_location',
            'access_token' => 'company_token',
            'token_type' => 'Company',
            'company_id' => null, // Explicitly null
        ]);

        Http::fake([
            'services.leadconnectorhq.com/oauth/locationToken' => Http::response([
                'access_token' => 'location_token',
                'refresh_token' => 'refresh_token',
                'expires_in' => 86400,
                'userType' => 'Location',
            ], 200),
        ]);

        // Should NOT throw "Company ID is required" error
        $result = $this->service->exchangeCompanyTokenForLocation($account, 'test_location');

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayNotHasKey('error', $result);
    }

    /**
     * Test location ID format validation
     */
    public function test_location_id_validation()
    {
        // Use reflection to test the protected validation method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('isValidLocationId');
        $method->setAccessible(true);

        // Valid location IDs
        $this->assertTrue($method->invoke($this->service, 'location_abc123'));
        $this->assertTrue($method->invoke($this->service, 'loc_xyz789_test'));
        $this->assertTrue($method->invoke($this->service, '0123456789abcdef'));

        // Invalid location IDs
        $this->assertFalse($method->invoke($this->service, '')); // Empty
        $this->assertFalse($method->invoke($this->service, 'short')); // Too short
        $this->assertFalse($method->invoke($this->service, 'contains spaces')); // Invalid characters
        $this->assertFalse($method->invoke($this->service, 'a')); // Too short
        $this->assertFalse($method->invoke($this->service, str_repeat('a', 51))); // Too long
    }

    /**
     * Test complete OAuth flow simulation
     */
    public function test_complete_oauth_flow_with_token_exchange()
    {
        // Step 1: Initial token exchange (authorization code → company token)
        Http::fake([
            'services.leadconnectorhq.com/oauth/token' => Http::response([
                'access_token' => 'company_access_token',
                'refresh_token' => 'company_refresh_token',
                'expires_in' => 86400,
                'userType' => 'Company',
                'userId' => 'user_123',
                // Note: No companyId or locationId in response (realistic scenario)
            ], 200),

            'services.leadconnectorhq.com/oauth/locationToken' => Http::response([
                'access_token' => 'location_access_token',
                'refresh_token' => 'location_refresh_token',
                'expires_in' => 86400,
                'userType' => 'Location',
                'locationId' => 'loc_test_123',
                'companyId' => 'comp_test_456',
            ], 200),

            'backend.leadconnectorhq.com/payments/custom-provider/provider*' => Http::response([
                'id' => 'provider_789',
                'name' => 'PayTR',
            ], 200),
        ]);

        // Simulate OAuth callback request
        $response = $this->get('/oauth/callback?' . http_build_query([
            'code' => 'test_authorization_code',
            'location_id' => 'loc_test_123', // Location ID from query parameter
        ]));

        // Verify successful redirect
        $this->assertTrue(
            $response->isRedirect() && (
                str_contains($response->headers->get('Location'), '/oauth/success') ||
                str_contains($response->headers->get('Location'), '/paytr/setup')
            ),
            'Should redirect to success or setup page'
        );

        // Verify account was created with correct tokens
        $account = HLAccount::where('location_id', 'loc_test_123')->first();
        $this->assertNotNull($account);
        $this->assertEquals('location_access_token', $account->location_access_token);
        $this->assertEquals('company_access_token', $account->company_access_token);
        $this->assertEquals('Location', $account->token_type);
        $this->assertEquals('comp_test_456', $account->company_id);
    }
}
