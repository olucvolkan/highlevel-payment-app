# SOP: Payment Callback Handling

> **Purpose**: Process PayTR payment callbacks securely
> **Trigger**: PayTR sends POST request after payment completion

## Overview

```
User completes payment in PayTR iframe
    ↓
PayTR → POST /payments/callback
    ↓
Validate hash
    ↓
Find payment by merchant_oid
    ↓
Update payment status
    ↓
Store card (if utoken provided)
    ↓
Send webhook to HighLevel
    ↓
Return "OK"
```

---

## Step 1: Receive Callback

**Endpoint**: `POST /payments/callback` (or GET for testing)

**Request Body** (from PayTR):
```
merchant_oid=ORDER_123&
status=success&
total_amount=10000&
hash=base64_encoded_hash&
payment_id=PT123456&
payment_type=card&
utoken=user_token_abc&
ctoken=card_token_xyz&
card_pan=4355084355084358&
card_type=credit&
card_brand=visa&
failed_reason_code=&
failed_reason_msg=
```

**Controller**:
```php
public function callback(Request $request): Response
{
    $callbackData = $request->all();

    $this->paymentLogger->logCallback('paytr', $callbackData);

    try {
        $success = $this->paymentService->processCallback($callbackData);

        if ($success) {
            return response('OK'); // MUST be exactly "OK"
        }

        return response('FAILED', 400);
    } catch (\Exception $e) {
        Log::error('PayTR callback processing failed', [
            'error' => $e->getMessage(),
            'data' => $callbackData,
        ]);

        return response('ERROR', 500);
    }
}
```

---

## Step 2: Validate Hash

**Formula**:
```
hash = base64_encode(
    hash_hmac('sha256',
        merchant_oid + merchant_salt + status + total_amount,
        merchant_key,
        true
    )
)
```

**Implementation**:
```php
// Find payment first to get account-specific credentials
$payment = Payment::with('hlAccount')
    ->where('merchant_oid', $callbackData['merchant_oid'])
    ->first();

if (!$payment) {
    Log::error('Payment not found for callback', [
        'merchant_oid' => $callbackData['merchant_oid']
    ]);
    return false;
}

// Create account-specific hash service
$hashService = PayTRHashService::forAccount($payment->hlAccount);

// Validate
if (!$hashService->validateCallback($callbackData)) {
    Log::error('Invalid PayTR callback hash', ['data' => $callbackData]);
    return false;
}
```

**Hash Service Method**:
```php
public function validateCallback(array $callbackData): bool
{
    $merchantOid = $callbackData['merchant_oid'];
    $status = $callbackData['status'];
    $totalAmount = $callbackData['total_amount'];
    $hash = $callbackData['hash'];

    $calculatedHash = base64_encode(
        hash_hmac('sha256',
            $merchantOid . $this->merchantSalt . $status . $totalAmount,
            $this->merchantKey,
            true
        )
    );

    return $hash === $calculatedHash;
}
```

---

## Step 3: Process Success

**Status**: `success`

**Actions**:
1. Update payment status
2. Store provider payment ID
3. Save metadata
4. Store card if utoken provided
5. Send webhook to HighLevel

**Implementation**:
```php
DB::transaction(function () use ($payment, $callbackData) {
    if ($callbackData['status'] === 'success') {
        // 1. Update payment
        $payment->markAsSuccess($callbackData['payment_id'] ?? null);
        $payment->update([
            'provider_payment_id' => $callbackData['payment_id'] ?? null,
            'metadata' => array_merge($payment->metadata ?? [], [
                'callback_data' => $callbackData,
            ]),
        ]);

        // 2. Handle card storage
        if (isset($callbackData['utoken'])) {
            $this->storePaymentMethod($payment, $callbackData);
        }

        // 3. Send webhook to HighLevel
        $this->highLevelService->sendPaymentCaptured($payment->hlAccount, [
            'chargeId' => $payment->charge_id,
            'transactionId' => $payment->transaction_id,
            'amount' => (int) ($payment->amount * 100),
            'chargedAt' => $payment->paid_at->timestamp,
        ]);

        // 4. Log success
        $this->paymentLogger->logPaymentSuccess($payment, $callbackData);
    }
});
```

---

## Step 4: Process Failure

**Status**: `failed`

**Actions**:
1. Update payment status
2. Store error message
3. Log failure
4. Create payment_failures record

**Implementation**:
```php
if ($callbackData['status'] === 'failed') {
    $payment->markAsFailed($callbackData['failed_reason_msg'] ?? 'Payment failed');

    // Log failure
    $this->logPaymentFailure($payment, $callbackData, $callbackData);

    $this->paymentLogger->logPaymentFailed($payment, $callbackData);
}
```

**Payment Model Method**:
```php
public function markAsFailed(?string $errorMessage = null): void
{
    $this->update([
        'status' => self::STATUS_FAILED,
        'error_message' => $errorMessage,
    ]);
}
```

**Failure Logging**:
```php
protected function logPaymentFailure(Payment $payment, array $response, array $request): void
{
    PaymentFailure::create([
        'payment_id' => $payment->id,
        'hl_account_id' => $payment->hl_account_id,
        'location_id' => $payment->location_id,
        'merchant_oid' => $payment->merchant_oid,
        'transaction_id' => $payment->transaction_id,
        'provider' => $payment->provider,
        'error_code' => $response['failed_reason_code'] ?? null,
        'error_message' => $response['failed_reason_msg'] ?? $response['error'] ?? 'Unknown error',
        'failure_reason' => $response['failed_reason_msg'] ?? null,
        'request_data' => $request,
        'response_data' => $response,
        'user_ip' => $payment->user_ip,
    ]);
}
```

---

## Step 5: Store Card

**Condition**: `utoken` present in callback

**Data Received**:
```
utoken=user_abc123
ctoken=card_xyz456
card_pan=4355084355084358
card_type=credit
card_brand=visa
```

**Implementation**:
```php
protected function storePaymentMethod(Payment $payment, array $callbackData): void
{
    if (!isset($callbackData['utoken']) || !$payment->contact_id) {
        return;
    }

    PaymentMethod::updateOrCreate(
        [
            'hl_account_id' => $payment->hl_account_id,
            'location_id' => $payment->location_id,
            'contact_id' => $payment->contact_id,
            'utoken' => $callbackData['utoken'],
        ],
        [
            'provider' => $payment->provider,
            'ctoken' => $callbackData['ctoken'] ?? null,
            'card_type' => $callbackData['card_type'] ?? null,
            'card_last_four' => $callbackData['card_last_four']
                ?? substr($callbackData['card_pan'] ?? '', -4),
            'card_brand' => $callbackData['card_brand']
                ?? $this->detectCardBrand($callbackData['card_pan'] ?? ''),
            'expiry_month' => $callbackData['card_exp_month'] ?? null,
            'expiry_year' => $callbackData['card_exp_year'] ?? null,
            'is_default' => false,
        ]
    );
}
```

---

## Step 6: Send HighLevel Webhook

**Endpoint**: `POST https://backend.leadconnectorhq.com/payments/custom-provider/webhook`

**Headers**:
```
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Payload**:
```json
{
  "type": "payment.captured",
  "chargeId": "chrg_123",
  "transactionId": "txn_001",
  "amount": 10000,
  "chargedAt": 1700000000
}
```

**Implementation**:
```php
public function sendPaymentCaptured(HLAccount $account, array $data)
{
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $account->access_token,
        'Content-Type' => 'application/json',
    ])->post(config('services.highlevel.webhook_url'), [
        'type' => 'payment.captured',
        'chargeId' => $data['chargeId'],
        'transactionId' => $data['transactionId'],
        'amount' => $data['amount'], // in cents
        'chargedAt' => $data['chargedAt'],
    ]);

    if ($response->successful()) {
        Log::info('HighLevel webhook sent successfully', [
            'charge_id' => $data['chargeId'],
        ]);
    } else {
        Log::error('HighLevel webhook failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    return $response;
}
```

---

## Step 7: Return Response

**Success**: Must return exactly `OK` (uppercase, no spaces)

```php
return response('OK');
```

**Failure**: Return `FAILED` with 400 status
```php
return response('FAILED', 400);
```

**Error**: Return `ERROR` with 500 status
```php
return response('ERROR', 500);
```

**Important**: PayTR expects `OK` string. If not received, it will retry the callback.

---

## Error Scenarios

### Payment Not Found
```php
$payment = Payment::where('merchant_oid', $merchantOid)->first();

if (!$payment) {
    Log::error('Payment not found for callback', [
        'merchant_oid' => $merchantOid
    ]);
    return response('FAILED', 400);
}
```

### Invalid Hash
```php
if (!$hashService->validateCallback($callbackData)) {
    Log::error('Invalid PayTR callback hash', [
        'received_hash' => $callbackData['hash'],
        'merchant_oid' => $merchantOid,
    ]);
    return response('FAILED', 400);
}
```

### Database Error
```php
try {
    DB::transaction(function () {
        // ... payment processing
    });
} catch (\Exception $e) {
    Log::error('Callback transaction failed', [
        'error' => $e->getMessage(),
        'merchant_oid' => $merchantOid,
    ]);
    return response('ERROR', 500);
}
```

---

## Logging

**All Callbacks Logged**:
```php
$this->paymentLogger->logCallback('paytr', $callbackData);
```

**Success Events**:
```php
$this->paymentLogger->logPaymentSuccess($payment, $callbackData);
```

**Failure Events**:
```php
$this->paymentLogger->logPaymentFailed($payment, $callbackData);
```

**Webhook Sent**:
```php
WebhookLog::create([
    'hl_account_id' => $account->id,
    'location_id' => $account->location_id,
    'source' => 'paytr',
    'event_type' => 'payment.captured',
    'payload' => $callbackData,
    'response' => $response->json(),
    'status_code' => $response->status(),
    'processed' => true,
    'processed_at' => now(),
]);
```

---

## Testing

### Manual Callback Test
```bash
curl -X POST https://your-domain.com/payments/callback \
  -d "merchant_oid=ORDER_123" \
  -d "status=success" \
  -d "total_amount=10000" \
  -d "hash=CALCULATED_HASH" \
  -d "payment_id=PT123456"
```

### Hash Calculation for Testing
```php
// Use your test credentials
$merchant_key = 'test_key';
$merchant_salt = 'test_salt';
$merchant_oid = 'ORDER_123';
$status = 'success';
$total_amount = '10000';

$hash = base64_encode(
    hash_hmac('sha256',
        $merchant_oid . $merchant_salt . $status . $total_amount,
        $merchant_key,
        true
    )
);

echo "Hash: " . $hash;
```

---

## Security Checklist

- [ ] Hash validation before processing
- [ ] Database transaction for atomicity
- [ ] No sensitive data in logs (hash values are OK)
- [ ] Return "OK" only after successful processing
- [ ] Account-specific credentials (not global)
- [ ] Error logging without exposing internals
- [ ] HTTPS only (PayTR requires it)

---

## Files

- **Controller**: `app/Http/Controllers/PaymentController.php` (callback method)
- **Service**: `app/Services/PaymentService.php` (processCallback method)
- **Hash Service**: `app/Services/PayTRHashService.php` (validateCallback method)
- **Provider**: `app/PaymentGateways/PayTRPaymentProvider.php` (validateCallback method)
- **Webhook Controller**: `app/Http/Controllers/WebhookController.php` (alternative endpoint)
