<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\HLAccount;
use App\Models\Payment;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class WebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_processes_successful_paytr_callback()
    {
        $account = HLAccount::factory()->create([
            'location_id' => 'loc_test_123',
        ]);

        $payment = Payment::factory()->create([
            'hl_account_id' => $account->id,
            'location_id' => $account->location_id,
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_PENDING,
            'amount' => 100.00,
        ]);

        // Generate valid PayTR hash
        $merchantKey = config('services.paytr.merchant_key');
        $merchantSalt = config('services.paytr.merchant_salt');
        $hash = base64_encode(hash_hmac('sha256',
            'ORDER_12345' . $merchantSalt . 'success' . '10000',
            $merchantKey,
            true
        ));

        $response = $this->postJson('/api/callbacks/paytr', [
            'merchant_oid' => 'ORDER_12345',
            'status' => 'success',
            'total_amount' => '10000',
            'hash' => $hash,
            'payment_id' => 'paytr_pay_123',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');

        $this->assertDatabaseHas('payments', [
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_SUCCESS,
            'provider_payment_id' => 'paytr_pay_123',
        ]);

        $this->assertDatabaseHas('webhook_logs', [
            'type' => WebhookLog::TYPE_INCOMING,
            'source' => 'paytr_callback',
            'status' => WebhookLog::STATUS_SUCCESS,
        ]);
    }

    /** @test */
    public function it_processes_failed_paytr_callback()
    {
        $account = HLAccount::factory()->create();
        $payment = Payment::factory()->create([
            'hl_account_id' => $account->id,
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_PENDING,
        ]);

        $merchantKey = config('services.paytr.merchant_key');
        $merchantSalt = config('services.paytr.merchant_salt');
        $hash = base64_encode(hash_hmac('sha256',
            'ORDER_12345' . $merchantSalt . 'failed' . '10000',
            $merchantKey,
            true
        ));

        $response = $this->postJson('/api/callbacks/paytr', [
            'merchant_oid' => 'ORDER_12345',
            'status' => 'failed',
            'total_amount' => '10000',
            'hash' => $hash,
            'failed_reason_msg' => 'Card declined',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_FAILED,
            'error_message' => 'Card declined',
        ]);

        $this->assertDatabaseHas('payment_failures', [
            'merchant_oid' => 'ORDER_12345',
            'error_message' => 'Card declined',
        ]);
    }

    /** @test */
    public function it_rejects_paytr_callback_with_invalid_hash()
    {
        $payment = Payment::factory()->create([
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_PENDING,
        ]);

        $response = $this->postJson('/api/callbacks/paytr', [
            'merchant_oid' => 'ORDER_12345',
            'status' => 'success',
            'total_amount' => '10000',
            'hash' => 'invalid_hash',
        ]);

        $response->assertStatus(400);

        // Payment should remain pending
        $this->assertDatabaseHas('payments', [
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /** @test */
    public function it_returns_error_when_paytr_callback_missing_required_fields()
    {
        $response = $this->postJson('/api/callbacks/paytr', [
            'merchant_oid' => 'ORDER_12345',
            // Missing status, total_amount, hash
        ]);

        $response->assertStatus(400)
                 ->assertSeeText('Missing required field');

        $this->assertDatabaseHas('webhook_logs', [
            'source' => 'paytr_callback',
            'status' => WebhookLog::STATUS_FAILED,
        ]);
    }

    /** @test */
    public function it_handles_app_install_marketplace_webhook()
    {
        $response = $this->postJson('/api/webhooks/marketplace', [
            'event' => 'app.install',
            'location_id' => 'loc_new_install_123',
            'company_id' => 'comp_123',
            'user_id' => 'user_123',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');

        $this->assertDatabaseHas('hl_accounts', [
            'location_id' => 'loc_new_install_123',
            'company_id' => 'comp_123',
            'user_id' => 'user_123',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'location_id' => 'loc_new_install_123',
            'action' => 'app_installed',
        ]);

        $this->assertDatabaseHas('webhook_logs', [
            'type' => WebhookLog::TYPE_INCOMING,
            'source' => 'highlevel_marketplace',
            'event' => 'highlevel_marketplace',
            'status' => WebhookLog::STATUS_SUCCESS,
        ]);
    }

    /** @test */
    public function it_handles_app_uninstall_marketplace_webhook()
    {
        $account = HLAccount::factory()->create([
            'location_id' => 'loc_uninstall_123',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/webhooks/marketplace', [
            'event' => 'app.uninstall',
            'location_id' => 'loc_uninstall_123',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');

        $this->assertDatabaseHas('hl_accounts', [
            'id' => $account->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'location_id' => 'loc_uninstall_123',
            'action' => 'app_uninstalled',
        ]);
    }

    /** @test */
    public function it_handles_app_update_marketplace_webhook()
    {
        $account = HLAccount::factory()->create([
            'location_id' => 'loc_update_123',
        ]);

        $response = $this->postJson('/api/webhooks/marketplace', [
            'event' => 'app.update',
            'location_id' => 'loc_update_123',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');

        $this->assertDatabaseHas('user_activity_logs', [
            'location_id' => 'loc_update_123',
            'action' => 'app_updated',
        ]);
    }

    /** @test */
    public function it_returns_error_when_marketplace_webhook_missing_event()
    {
        $response = $this->postJson('/api/webhooks/marketplace', [
            'location_id' => 'loc_123',
        ]);

        $response->assertStatus(400)
                 ->assertSeeText('Missing event');
    }

    /** @test */
    public function it_returns_error_when_install_webhook_missing_location_id()
    {
        $response = $this->postJson('/api/webhooks/marketplace', [
            'event' => 'app.install',
            'company_id' => 'comp_123',
        ]);

        $response->assertStatus(400)
                 ->assertSeeText('Missing location_id');
    }

    /** @test */
    public function it_handles_unknown_marketplace_event_gracefully()
    {
        $response = $this->postJson('/api/webhooks/marketplace', [
            'event' => 'app.unknown_event',
            'location_id' => 'loc_123',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');

        $this->assertDatabaseHas('webhook_logs', [
            'source' => 'highlevel_marketplace',
            'status' => WebhookLog::STATUS_SUCCESS,
        ]);
    }

    /** @test */
    public function it_handles_highlevel_payment_webhook()
    {
        $account = HLAccount::factory()->create([
            'location_id' => 'loc_payment_123',
        ]);

        $response = $this->postJson('/api/webhooks/highlevel', [
            'event' => 'payment.request',
            'location_id' => 'loc_payment_123',
            'amount' => 100.00,
            'currency' => 'TRY',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');

        $this->assertDatabaseHas('user_activity_logs', [
            'location_id' => 'loc_payment_123',
            'action' => 'highlevel_webhook_received',
        ]);

        $this->assertDatabaseHas('webhook_logs', [
            'type' => WebhookLog::TYPE_INCOMING,
            'source' => 'highlevel_payment',
            'status' => WebhookLog::STATUS_SUCCESS,
        ]);
    }

    /** @test */
    public function it_returns_error_when_highlevel_webhook_missing_location_id()
    {
        $response = $this->postJson('/api/webhooks/highlevel', [
            'event' => 'payment.request',
        ]);

        $response->assertStatus(400)
                 ->assertSeeText('Missing required fields');
    }

    /** @test */
    public function it_returns_404_when_highlevel_webhook_account_not_found()
    {
        $response = $this->postJson('/api/webhooks/highlevel', [
            'event' => 'payment.request',
            'location_id' => 'loc_nonexistent',
        ]);

        $response->assertStatus(404)
                 ->assertSeeText('Account not found');
    }

    /** @test */
    public function it_handles_subscription_request_webhook()
    {
        $account = HLAccount::factory()->create([
            'location_id' => 'loc_subscription_123',
        ]);

        $response = $this->postJson('/api/webhooks/highlevel', [
            'event' => 'subscription.request',
            'location_id' => 'loc_subscription_123',
            'plan_id' => 'plan_123',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');
    }

    /** @test */
    public function it_logs_all_incoming_webhooks()
    {
        $initialCount = WebhookLog::count();

        $account = HLAccount::factory()->create([
            'location_id' => 'loc_test_123',
        ]);

        $this->postJson('/api/webhooks/marketplace', [
            'event' => 'app.install',
            'location_id' => 'loc_test_123',
        ]);

        $this->assertEquals($initialCount + 1, WebhookLog::count());

        $log = WebhookLog::latest()->first();
        $this->assertEquals(WebhookLog::TYPE_INCOMING, $log->type);
        $this->assertNotNull($log->payload);
        $this->assertNotNull($log->received_at);
    }

    /** @test */
    public function it_stores_card_token_in_paytr_callback()
    {
        $account = HLAccount::factory()->create();
        $payment = Payment::factory()->create([
            'hl_account_id' => $account->id,
            'merchant_oid' => 'ORDER_12345',
            'contact_id' => 'contact_123',
            'status' => Payment::STATUS_PENDING,
        ]);

        $merchantKey = config('services.paytr.merchant_key');
        $merchantSalt = config('services.paytr.merchant_salt');
        $hash = base64_encode(hash_hmac('sha256',
            'ORDER_12345' . $merchantSalt . 'success' . '10000',
            $merchantKey,
            true
        ));

        $response = $this->postJson('/api/callbacks/paytr', [
            'merchant_oid' => 'ORDER_12345',
            'status' => 'success',
            'total_amount' => '10000',
            'hash' => $hash,
            'payment_id' => 'paytr_pay_123',
            'utoken' => 'user_token_123',
            'ctoken' => 'card_token_123',
            'card_type' => 'visa',
            'card_last_four' => '4242',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payment_methods', [
            'hl_account_id' => $account->id,
            'contact_id' => 'contact_123',
            'utoken' => 'user_token_123',
            'ctoken' => 'card_token_123',
            'card_last_four' => '4242',
        ]);
    }
}
