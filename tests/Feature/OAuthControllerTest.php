<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\HLAccount;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

class OAuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_initiates_oauth_authorization()
    {
        $response = $this->get('/oauth/authorize');

        $response->assertRedirect();
        $this->assertStringContainsString('marketplace.gohighlevel.com/oauth/chooselocation', $response->headers->get('Location'));
        $this->assertStringContainsString('response_type=code', $response->headers->get('Location'));
        $this->assertStringContainsString('client_id=', $response->headers->get('Location'));
    }

    /** @test */
    public function it_returns_error_when_callback_missing_code()
    {
        $response = $this->get('/oauth/callback');

        $response->assertRedirect(route('oauth.error'));
        $this->assertEquals('Authorization code missing', session('error'));
    }

    /** @test */
    public function it_handles_successful_oauth_callback()
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test_access_token_123',
                'refresh_token' => 'test_refresh_token_123',
                'expires_in' => 3600,
                'location_id' => 'loc_test_123',
            ], 200),
            '*/payments/create-integration' => Http::response([
                '_id' => 'int_test_123',
                'status' => 'success',
            ], 200),
        ]);

        $response = $this->get('/oauth/callback?' . http_build_query([
            'code' => 'test_auth_code',
            'location_id' => 'loc_test_123',
            'state' => 'test_state',
        ]));

        // Should redirect to PayTR setup since credentials are not configured
        $response->assertRedirect(route('paytr.setup', ['location_id' => 'loc_test_123']));
        $this->assertStringContainsString('HighLevel integration completed', session('success'));

        $this->assertDatabaseHas('hl_accounts', [
            'location_id' => 'loc_test_123',
            'access_token' => 'test_access_token_123',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'location_id' => 'loc_test_123',
            'action' => 'oauth_success',
        ]);
    }

    /** @test */
    public function it_handles_oauth_callback_token_exchange_failure()
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Authorization code is invalid',
            ], 400),
        ]);

        $response = $this->get('/oauth/callback?' . http_build_query([
            'code' => 'invalid_code',
            'location_id' => 'loc_test_123',
        ]));

        $response->assertRedirect(route('oauth.error'));
        $this->assertStringContainsString('Token exchange failed', session('error'));
    }

    /** @test */
    public function it_updates_existing_account_on_callback()
    {
        $existingAccount = HLAccount::factory()->create([
            'location_id' => 'loc_test_123',
            'access_token' => 'old_token',
            'is_active' => false,
        ]);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 3600,
                'location_id' => 'loc_test_123',
            ], 200),
            '*/payments/create-integration' => Http::response([
                '_id' => 'int_test_123',
            ], 200),
        ]);

        $response = $this->get('/oauth/callback?' . http_build_query([
            'code' => 'test_code',
            'location_id' => 'loc_test_123',
        ]));

        // Should redirect to PayTR setup since credentials are not configured
        $response->assertRedirect(route('paytr.setup', ['location_id' => 'loc_test_123']));

        $this->assertDatabaseHas('hl_accounts', [
            'id' => $existingAccount->id,
            'location_id' => 'loc_test_123',
            'access_token' => 'new_access_token',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_handles_integration_creation_failure_gracefully()
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 3600,
                'location_id' => 'loc_test_123',
            ], 200),
            '*/payments/create-integration' => Http::response([
                'error' => 'Integration creation failed',
            ], 500),
        ]);

        $response = $this->get('/oauth/callback?' . http_build_query([
            'code' => 'test_code',
            'location_id' => 'loc_test_123',
        ]));

        // Should still succeed even if integration creation fails, redirect to PayTR setup
        $response->assertRedirect(route('paytr.setup', ['location_id' => 'loc_test_123']));

        $this->assertDatabaseHas('hl_accounts', [
            'location_id' => 'loc_test_123',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_displays_oauth_success_page()
    {
        $response = $this->get('/oauth/success');

        $response->assertStatus(200)
                 ->assertViewIs('oauth.success')
                 ->assertSee('Integration Successful');
    }

    /** @test */
    public function it_displays_oauth_success_page_with_custom_message()
    {
        $response = $this->withSession(['success' => 'Custom success message'])
                         ->get('/oauth/success');

        $response->assertStatus(200)
                 ->assertViewIs('oauth.success')
                 ->assertViewHas('message', 'Custom success message');
    }

    /** @test */
    public function it_displays_oauth_error_page()
    {
        $response = $this->get('/oauth/error');

        $response->assertStatus(200)
                 ->assertViewIs('oauth.error')
                 ->assertSee('Integration Failed');
    }

    /** @test */
    public function it_displays_oauth_error_page_with_custom_error()
    {
        $response = $this->withSession(['error' => 'Custom error message'])
                         ->get('/oauth/error');

        $response->assertStatus(200)
                 ->assertViewIs('oauth.error')
                 ->assertViewHas('error', 'Custom error message');
    }

    /** @test */
    public function it_handles_app_uninstall()
    {
        $account = HLAccount::factory()->create([
            'location_id' => 'loc_test_123',
            'is_active' => true,
        ]);

        $response = $this->post('/oauth/uninstall', [
            'location_id' => 'loc_test_123',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');

        $this->assertDatabaseHas('hl_accounts', [
            'id' => $account->id,
            'location_id' => 'loc_test_123',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'location_id' => 'loc_test_123',
            'action' => 'oauth_uninstall',
        ]);
    }

    /** @test */
    public function it_returns_error_when_uninstall_missing_location_id()
    {
        $response = $this->post('/oauth/uninstall', []);

        $response->assertStatus(400)
                 ->assertSeeText('Missing location_id');
    }

    /** @test */
    public function it_handles_uninstall_for_nonexistent_account()
    {
        $response = $this->post('/oauth/uninstall', [
            'location_id' => 'loc_nonexistent',
        ]);

        // Should return OK even if account doesn't exist
        $response->assertStatus(200)
                 ->assertSeeText('OK');
    }

    /** @test */
    public function it_stores_oauth_state_in_session()
    {
        $response = $this->get('/oauth/authorize');

        $this->assertNotNull(session('oauth_state'));
        $this->assertEquals(40, strlen(session('oauth_state')));
    }

    /** @test */
    public function it_includes_all_required_scopes_in_authorization_url()
    {
        $response = $this->get('/oauth/authorize');

        $redirectUrl = $response->headers->get('Location');
        $decodedUrl = urldecode($redirectUrl);

        // Check in decoded URL since scopes are URL encoded
        $this->assertStringContainsString('payments/orders.readonly', $decodedUrl);
        $this->assertStringContainsString('payments/orders.write', $decodedUrl);
        $this->assertStringContainsString('payments/subscriptions.readonly', $decodedUrl);
        $this->assertStringContainsString('payments/transactions.readonly', $decodedUrl);
        $this->assertStringContainsString('payments/custom-provider.readonly', $decodedUrl);
        $this->assertStringContainsString('payments/custom-provider.write', $decodedUrl);
        $this->assertStringContainsString('products.readonly', $decodedUrl);
        $this->assertStringContainsString('products/prices.readonly', $decodedUrl);
    }

    /** @test */
    public function it_logs_account_created_on_new_installation()
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'refresh_token' => 'test_refresh',
                'expires_in' => 3600,
                'location_id' => 'loc_new_123',
            ], 200),
            '*/payments/create-integration' => Http::response(['_id' => 'int_123'], 200),
        ]);

        $this->get('/oauth/callback?' . http_build_query([
            'code' => 'test_code',
            'location_id' => 'loc_new_123',
        ]));

        $this->assertDatabaseHas('user_activity_logs', [
            'location_id' => 'loc_new_123',
            'action' => 'oauth_success',
        ]);
    }
}
