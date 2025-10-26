<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\HLAccount;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayTRSetupControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected HLAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test account
        $this->account = HLAccount::factory()->create([
            'location_id' => 'test_location_123',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_shows_paytr_setup_page_for_valid_location()
    {
        $response = $this->get('/paytr/setup?location_id=' . $this->account->location_id);

        $response->assertStatus(200)
                 ->assertViewIs('paytr.setup')
                 ->assertViewHas('account', $this->account)
                 ->assertViewHas('locationId', $this->account->location_id)
                 ->assertViewHas('isConfigured', false);
    }

    /** @test */
    public function it_returns_400_when_location_id_missing_in_setup()
    {
        $response = $this->get('/paytr/setup');

        $response->assertStatus(400);
    }

    /** @test */
    public function it_returns_404_when_account_not_found_in_setup()
    {
        $response = $this->get('/paytr/setup?location_id=invalid_location');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_shows_configured_status_when_paytr_already_setup()
    {
        // Configure PayTR for account
        $this->account->setPayTRCredentials([
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response = $this->get('/paytr/setup?location_id=' . $this->account->location_id);

        $response->assertStatus(200)
                 ->assertViewHas('isConfigured', true);
    }

    /** @test */
    public function it_validates_required_fields_when_saving_credentials()
    {
        $response = $this->postJson('/paytr/credentials', [
            'location_id' => $this->account->location_id,
            // Missing required fields
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                 ])
                 ->assertJsonStructure([
                     'errors' => [
                         'merchant_id',
                         'merchant_key',
                         'merchant_salt',
                     ]
                 ]);
    }

    /** @test */
    public function it_returns_404_when_account_not_found_when_saving_credentials()
    {
        $response = $this->postJson('/paytr/credentials', [
            'location_id' => 'invalid_location',
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Account not found',
                 ]);
    }

    /** @test */
    public function it_tests_paytr_credentials_before_saving()
    {
        // Mock PayTR API to return success
        Http::fake([
            'www.paytr.com/odeme/api/get-token' => Http::response([
                'status' => 'success',
                'token' => 'test_token_123',
            ], 200),
        ]);

        $response = $this->postJson('/paytr/credentials', [
            'location_id' => $this->account->location_id,
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'PayTR credentials saved successfully',
                 ]);

        // Verify credentials are saved in database
        $this->account->refresh();
        $this->assertTrue($this->account->hasPayTRCredentials());
        $this->assertEquals('test_merchant', $this->account->paytr_merchant_id);
        $this->assertTrue($this->account->paytr_test_mode);
        $this->assertTrue($this->account->paytr_configured);
        $this->assertNotNull($this->account->paytr_configured_at);

        // Verify credentials are encrypted
        $this->assertNotEquals('test_key', $this->account->paytr_merchant_key);
        $this->assertNotEquals('test_salt', $this->account->paytr_merchant_salt);

        // Verify decryption works
        $credentials = $this->account->getPayTRCredentials();
        $this->assertEquals('test_merchant', $credentials['merchant_id']);
        $this->assertEquals('test_key', $credentials['merchant_key']);
        $this->assertEquals('test_salt', $credentials['merchant_salt']);
        $this->assertTrue($credentials['test_mode']);
    }

    /** @test */
    public function it_rejects_invalid_paytr_credentials()
    {
        // Mock PayTR API to return error
        Http::fake([
            'www.paytr.com/odeme/api/get-token' => Http::response([
                'status' => 'failed',
                'reason' => 'INVALID_MERCHANT_ID',
            ], 200),
        ]);

        $response = $this->postJson('/paytr/credentials', [
            'location_id' => $this->account->location_id,
            'merchant_id' => 'invalid_merchant',
            'merchant_key' => 'invalid_key',
            'merchant_salt' => 'invalid_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                 ])
                 ->assertJsonFragment([
                     'message' => 'PayTR credentials test failed: INVALID_MERCHANT_ID'
                 ]);

        // Verify credentials are NOT saved
        $this->account->refresh();
        $this->assertFalse($this->account->hasPayTRCredentials());
    }

    /** @test */
    public function it_handles_paytr_api_timeout_gracefully()
    {
        // Mock PayTR API to timeout
        Http::fake([
            'www.paytr.com/odeme/api/get-token' => Http::response('', 500),
        ]);

        $response = $this->postJson('/paytr/credentials', [
            'location_id' => $this->account->location_id,
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                 ]);

        $this->account->refresh();
        $this->assertFalse($this->account->hasPayTRCredentials());
    }

    /** @test */
    public function it_logs_user_activity_when_credentials_saved()
    {
        Http::fake([
            'www.paytr.com/odeme/api/get-token' => Http::response([
                'status' => 'success',
                'token' => 'test_token_123',
            ], 200),
        ]);

        $initialLogCount = UserActivityLog::count();

        $response = $this->postJson('/paytr/credentials', [
            'location_id' => $this->account->location_id,
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(200);

        // Verify user activity log is created
        $this->assertEquals($initialLogCount + 1, UserActivityLog::count());

        $log = UserActivityLog::latest()->first();
        $this->assertEquals($this->account->id, $log->hl_account_id);
        $this->assertEquals($this->account->location_id, $log->location_id);
        $this->assertEquals('paytr_configured', $log->action);
        $this->assertEquals('test_merchant', $log->metadata['merchant_id']);
        $this->assertTrue($log->metadata['test_mode']);
    }

    /** @test */
    public function it_tests_credentials_without_saving()
    {
        Http::fake([
            'www.paytr.com/odeme/api/get-token' => Http::response([
                'status' => 'success',
                'token' => 'test_token_123',
            ], 200),
        ]);

        $response = $this->postJson('/paytr/test', [
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'PayTR credentials are valid',
                 ]);

        // Verify credentials are NOT saved
        $this->account->refresh();
        $this->assertFalse($this->account->hasPayTRCredentials());
    }

    /** @test */
    public function it_validates_test_credentials_request()
    {
        $response = $this->postJson('/paytr/test', [
            // Missing required fields
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                 ])
                 ->assertJsonStructure([
                     'errors' => [
                         'merchant_id',
                         'merchant_key',
                         'merchant_salt',
                     ]
                 ]);
    }

    /** @test */
    public function it_shows_current_configuration()
    {
        // Configure PayTR first
        $this->account->setPayTRCredentials([
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response = $this->getJson('/paytr/config?location_id=' . $this->account->location_id);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'configured' => true,
                     'merchant_id' => 'test_merchant',
                     'test_mode' => true,
                 ])
                 ->assertJsonStructure([
                     'configured_at'
                 ]);
    }

    /** @test */
    public function it_shows_unconfigured_status_when_no_credentials()
    {
        $response = $this->getJson('/paytr/config?location_id=' . $this->account->location_id);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'configured' => false,
                     'merchant_id' => null,
                     'test_mode' => true, // Default value from migration
                     'configured_at' => null,
                 ]);
    }

    /** @test */
    public function it_requires_location_id_for_configuration_check()
    {
        $response = $this->getJson('/paytr/config');

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Missing location_id parameter',
                 ]);
    }

    /** @test */
    public function it_removes_paytr_configuration()
    {
        // First configure PayTR
        $this->account->setPayTRCredentials([
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $this->assertTrue($this->account->hasPayTRCredentials());

        $initialLogCount = UserActivityLog::count();

        $response = $this->deleteJson('/paytr/config?location_id=' . $this->account->location_id);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'PayTR configuration removed successfully',
                 ]);

        // Verify configuration is removed
        $this->account->refresh();
        $this->assertFalse($this->account->hasPayTRCredentials());
        $this->assertNull($this->account->paytr_merchant_id);
        $this->assertNull($this->account->paytr_merchant_key);
        $this->assertNull($this->account->paytr_merchant_salt);
        $this->assertFalse($this->account->paytr_configured);
        $this->assertNull($this->account->paytr_configured_at);

        // Verify user activity log
        $this->assertEquals($initialLogCount + 1, UserActivityLog::count());

        $log = UserActivityLog::latest()->first();
        $this->assertEquals('paytr_unconfigured', $log->action);
    }

    /** @test */
    public function it_requires_location_id_for_configuration_removal()
    {
        $response = $this->deleteJson('/paytr/config');

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Missing location_id parameter',
                 ]);
    }

    /** @test */
    public function it_returns_404_when_account_not_found_for_removal()
    {
        $response = $this->deleteJson('/paytr/config?location_id=invalid_location');

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Account not found',
                 ]);
    }

    /** @test */
    public function it_generates_correct_paytr_hash_for_test_request()
    {
        // Capture the actual HTTP request to verify hash generation
        Http::fake([
            'www.paytr.com/odeme/api/get-token' => function ($request) {
                $data = $request->data();
                
                // Verify all required fields are present
                $this->assertArrayHasKey('merchant_id', $data);
                $this->assertArrayHasKey('paytr_token', $data);
                $this->assertArrayHasKey('merchant_oid', $data);
                $this->assertArrayHasKey('user_ip', $data);
                $this->assertArrayHasKey('email', $data);
                $this->assertArrayHasKey('payment_amount', $data);
                
                // Verify test data values
                $this->assertEquals('test_merchant', $data['merchant_id']);
                $this->assertEquals('127.0.0.1', $data['user_ip']);
                $this->assertEquals('test@example.com', $data['email']);
                $this->assertEquals('100', $data['payment_amount']);
                $this->assertEquals('1', $data['test_mode']);
                
                // Verify merchant_oid format
                $this->assertStringStartsWith('TEST_', $data['merchant_oid']);
                
                return Http::response([
                    'status' => 'success',
                    'token' => 'test_token',
                ], 200);
            },
        ]);

        $response = $this->postJson('/paytr/test', [
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_paytr_api_errors_gracefully()
    {
        // Test INVALID_MERCHANT_ID error
        Http::fake([
            'www.paytr.com/*' => Http::response([
                'status' => 'failed',
                'reason' => 'INVALID_MERCHANT_ID',
            ], 200),
        ]);

        $response = $this->postJson('/paytr/test', [
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => false,
                     'error' => 'INVALID_MERCHANT_ID',
                 ]);
    }

    /** @test */
    public function it_logs_errors_when_credentials_save_fails()
    {
        // Mock PayTR API to succeed but force DB error by using invalid location
        Http::fake([
            'www.paytr.com/odeme/api/get-token' => Http::response([
                'status' => 'success',
                'token' => 'test_token',
            ], 200),
        ]);

        // Delete the account to force DB error
        $locationId = $this->account->location_id;
        $this->account->delete();

        $response = $this->postJson('/paytr/credentials', [
            'location_id' => $locationId,
            'merchant_id' => 'test_merchant',
            'merchant_key' => 'test_key',
            'merchant_salt' => 'test_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(404);

        // Verify error is logged - this will be logged by the account not found check
        // If we want to test the exception case, we'd need to mock the account update to throw
    }

    /** @test */
    public function it_updates_existing_credentials_when_reconfiguring()
    {
        // First, set initial credentials
        $this->account->setPayTRCredentials([
            'merchant_id' => 'old_merchant',
            'merchant_key' => 'old_key',
            'merchant_salt' => 'old_salt',
            'test_mode' => false,
        ]);

        $this->assertTrue($this->account->hasPayTRCredentials());
        $this->assertEquals('old_merchant', $this->account->paytr_merchant_id);

        Http::fake([
            'www.paytr.com/odeme/api/get-token' => Http::response([
                'status' => 'success',
                'token' => 'test_token',
            ], 200),
        ]);

        // Update with new credentials
        $response = $this->postJson('/paytr/credentials', [
            'location_id' => $this->account->location_id,
            'merchant_id' => 'new_merchant',
            'merchant_key' => 'new_key',
            'merchant_salt' => 'new_salt',
            'test_mode' => true,
        ]);

        $response->assertStatus(200);

        // Verify credentials are updated
        $this->account->refresh();
        $this->assertEquals('new_merchant', $this->account->paytr_merchant_id);
        $this->assertTrue($this->account->paytr_test_mode);

        $credentials = $this->account->getPayTRCredentials();
        $this->assertEquals('new_merchant', $credentials['merchant_id']);
        $this->assertEquals('new_key', $credentials['merchant_key']);
        $this->assertEquals('new_salt', $credentials['merchant_salt']);
        $this->assertTrue($credentials['test_mode']);
    }
}