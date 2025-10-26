<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\HLAccount;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

class PaymentControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected HLAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test account
        $this->account = HLAccount::factory()->create([
            'location_id' => 'test_location_123',
        ]);
    }

    /** @test */
    public function it_returns_error_when_account_not_found_in_query()
    {
        $response = $this->postJson('/api/payments/query', [
            'type' => 'verify',
            'transactionId' => 'txn_test_123',
        ], [
            'X-Location-Id' => 'invalid_location',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['error' => 'Invalid account']);
    }

    /** @test */
    public function it_verifies_successful_payment()
    {
        $payment = Payment::factory()->successful()->create([
            'hl_account_id' => $this->account->id,
            'location_id' => $this->account->location_id,
            'transaction_id' => 'txn_test_123',
        ]);

        $response = $this->postJson('/api/payments/query', [
            'type' => 'verify',
            'transactionId' => 'txn_test_123',
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_verifies_failed_payment()
    {
        $payment = Payment::factory()->failed()->create([
            'hl_account_id' => $this->account->id,
            'location_id' => $this->account->location_id,
            'transaction_id' => 'txn_test_456',
        ]);

        $response = $this->postJson('/api/payments/query', [
            'type' => 'verify',
            'transactionId' => 'txn_test_456',
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['failed' => true]);
    }

    /** @test */
    public function it_lists_payment_methods()
    {
        $this->markTestSkipped('Requires PayTR API credentials - third-party dependency');

        $contactId = 'contact_test_123';

        PaymentMethod::factory()->count(3)->create([
            'hl_account_id' => $this->account->id,
            'location_id' => $this->account->location_id,
            'contact_id' => $contactId,
        ]);

        $response = $this->postJson('/api/payments/query', [
            'type' => 'list_payment_methods',
            'contactId' => $contactId,
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'paymentMethods' => [
                         '*' => ['id', 'type', 'title', 'subTitle', 'expiry', 'imageUrl']
                     ]
                 ])
                 ->assertJsonCount(3, 'paymentMethods');
    }

    /** @test */
    public function it_returns_error_when_listing_payment_methods_without_contact_id()
    {
        $response = $this->postJson('/api/payments/query', [
            'type' => 'list_payment_methods',
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'Contact ID required']);
    }

    /** @test */
    public function it_charges_payment_method()
    {
        $this->markTestSkipped('Requires PayTR API credentials - third-party dependency');

        $paymentMethod = PaymentMethod::factory()->create([
            'hl_account_id' => $this->account->id,
            'location_id' => $this->account->location_id,
            'contact_id' => 'contact_test_123',
        ]);

        Http::fake([
            'www.paytr.com/*' => Http::response([
                'status' => 'success',
                'token' => 'test_token_123',
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/query', [
            'type' => 'charge_payment',
            'paymentMethodId' => $paymentMethod->id,
            'contactId' => 'contact_test_123',
            'transactionId' => 'txn_charge_123',
            'amount' => 100.50,
            'email' => 'test@example.com',
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_returns_error_when_charging_with_invalid_payment_method()
    {
        $this->markTestSkipped('Requires PayTR API credentials - third-party dependency');

        $response = $this->postJson('/api/payments/query', [
            'type' => 'charge_payment',
            'paymentMethodId' => 'invalid_id',
            'contactId' => 'contact_test_123',
            'transactionId' => 'txn_charge_123',
            'amount' => 100.50,
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(404)
                 ->assertJson(['error' => 'Payment method not found']);
    }

    /** @test */
    public function it_processes_refund()
    {
        $this->markTestSkipped('Requires PayTR API credentials - third-party dependency');

        $payment = Payment::factory()->successful()->create([
            'hl_account_id' => $this->account->id,
            'location_id' => $this->account->location_id,
            'charge_id' => 'ch_test_123',
            'amount' => 100.00,
        ]);

        Http::fake([
            'www.paytr.com/*' => Http::response([
                'status' => 'success',
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/query', [
            'type' => 'refund',
            'chargeId' => 'ch_test_123',
            'amount' => 50.00,
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PARTIAL_REFUND,
        ]);
    }

    /** @test */
    public function it_returns_error_when_refunding_nonexistent_payment()
    {
        $response = $this->postJson('/api/payments/query', [
            'type' => 'refund',
            'chargeId' => 'ch_invalid',
            'amount' => 50.00,
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(404)
                 ->assertJson(['error' => 'Payment not found']);
    }

    /** @test */
    public function it_returns_not_implemented_for_create_subscription()
    {
        $response = $this->postJson('/api/payments/query', [
            'type' => 'create_subscription',
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(501)
                 ->assertJson(['error' => 'Subscriptions not yet implemented']);
    }

    /** @test */
    public function it_returns_error_for_invalid_operation_type()
    {
        $response = $this->postJson('/api/payments/query', [
            'type' => 'invalid_operation',
        ], [
            'X-Location-Id' => $this->account->location_id,
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'Invalid operation type']);
    }

    /** @test */
    public function it_displays_payment_page()
    {
        $this->markTestSkipped('Requires PayTR API credentials - third-party dependency');

        Http::fake([
            'www.paytr.com/*' => Http::response([
                'status' => 'success',
                'token' => 'test_payment_token',
            ], 200),
        ]);

        $response = $this->get('/payments/page?' . http_build_query([
            'amount' => 100.50,
            'email' => 'test@example.com',
            'transactionId' => 'txn_page_test',
            'locationId' => $this->account->location_id,
        ]));

        $response->assertStatus(200)
                 ->assertViewIs('payments.iframe')
                 ->assertViewHas('iframeUrl')
                 ->assertViewHas('merchantOid')
                 ->assertViewHas('transactionId', 'txn_page_test');
    }

    /** @test */
    public function it_returns_error_when_payment_page_has_invalid_parameters()
    {
        $response = $this->get('/payments/page?' . http_build_query([
            'amount' => 100.50,
            // Missing email and transactionId
            'locationId' => $this->account->location_id,
        ]));

        $response->assertStatus(400);
    }

    /** @test */
    public function it_handles_paytr_callback()
    {
        $this->markTestSkipped('Requires PayTR API credentials - third-party dependency');

        $payment = Payment::factory()->create([
            'hl_account_id' => $this->account->id,
            'location_id' => $this->account->location_id,
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_PENDING,
        ]);

        // Calculate valid PayTR hash
        $merchantKey = config('services.paytr.merchant_key');
        $merchantSalt = config('services.paytr.merchant_salt');
        $hash = base64_encode(hash_hmac('sha256',
            'ORDER_12345' . $merchantSalt . 'success' . '10000',
            $merchantKey,
            true
        ));

        $response = $this->post('/payments/callback', [
            'merchant_oid' => 'ORDER_12345',
            'status' => 'success',
            'total_amount' => '10000',
            'hash' => $hash,
            'payment_id' => 'pay_test_123',
        ]);

        $response->assertStatus(200)
                 ->assertSeeText('OK');

        $this->assertDatabaseHas('payments', [
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_SUCCESS,
        ]);
    }

    /** @test */
    public function it_rejects_callback_with_invalid_hash()
    {
        $payment = Payment::factory()->create([
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_PENDING,
        ]);

        $response = $this->post('/payments/callback', [
            'merchant_oid' => 'ORDER_12345',
            'status' => 'success',
            'total_amount' => '10000',
            'hash' => 'invalid_hash',
        ]);

        $response->assertStatus(400);

        // Payment should still be pending
        $this->assertDatabaseHas('payments', [
            'merchant_oid' => 'ORDER_12345',
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /** @test */
    public function it_displays_success_page()
    {
        $payment = Payment::factory()->successful()->create([
            'merchant_oid' => 'ORDER_12345',
            'amount' => 100.00,
            'currency' => 'TRY',
        ]);

        $response = $this->get('/payments/success?merchant_oid=ORDER_12345');

        $response->assertStatus(200)
                 ->assertViewIs('payments.success')
                 ->assertViewHas('payment', $payment);
    }

    /** @test */
    public function it_displays_error_page()
    {
        $payment = Payment::factory()->failed()->create([
            'merchant_oid' => 'ORDER_12345',
            'error_message' => 'Card declined',
        ]);

        $response = $this->get('/payments/error?merchant_oid=ORDER_12345&error=Card declined');

        $response->assertStatus(200)
                 ->assertViewIs('payments.error')
                 ->assertViewHas('payment', $payment)
                 ->assertViewHas('error', 'Card declined');
    }

    /** @test */
    public function it_checks_payment_status_by_merchant_oid()
    {
        $payment = Payment::factory()->successful()->create([
            'merchant_oid' => 'ORDER_12345',
            'transaction_id' => 'txn_test_123',
            'charge_id' => 'ch_test_123',
            'amount' => 100.00,
        ]);

        $response = $this->postJson('/api/payments/status', [
            'merchantOid' => 'ORDER_12345',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'chargeId' => 'ch_test_123',
                     'transactionId' => 'txn_test_123',
                     'amount' => 100.00,
                 ]);
    }

    /** @test */
    public function it_checks_payment_status_by_transaction_id()
    {
        $payment = Payment::factory()->create([
            'merchant_oid' => 'ORDER_12345',
            'transaction_id' => 'txn_test_123',
            'status' => Payment::STATUS_PENDING,
        ]);

        $response = $this->postJson('/api/payments/status', [
            'transactionId' => 'txn_test_123',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'pending',
                     'transactionId' => 'txn_test_123',
                 ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_payment_status()
    {
        $response = $this->postJson('/api/payments/status', [
            'merchantOid' => 'ORDER_INVALID',
        ]);

        $response->assertStatus(404)
                 ->assertJson(['status' => 'not_found']);
    }

    /** @test */
    public function it_returns_error_when_status_check_missing_identifier()
    {
        $response = $this->postJson('/api/payments/status', []);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'Missing payment identifier']);
    }
}
