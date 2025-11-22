# PayTR Integration

> **Provider**: PayTRPaymentProvider
> **API**: PayTR Direct API (iframe)
> **Documentation**: `technical_documentation/PayTR Direkt API/`

## Implementation Overview

**Files**:
- `app/PaymentGateways/PayTRPaymentProvider.php` - Main provider class
- `app/Services/PayTRHashService.php` - HMAC signature generation
- `app/Http/Controllers/PayTRSetupController.php` - Credentials management

**Status**: ✅ Fully implemented and tested (100%)

---

## Payment Flow

```
1. User initiates payment in HighLevel
2. HighLevel calls /payments/page
3. PaymentService creates Payment record
4. PayTRPaymentProvider.initializePayment()
   ├─ Generates HMAC-SHA256 token
   ├─ Calls POST /odeme/api/get-token
   └─ Returns iframe URL
5. Frontend renders PayTR iframe
6. User completes payment in iframe
7. PayTR calls /payments/callback
8. PaymentService.processCallback()
   ├─ Validates hash
   ├─ Updates payment status
   ├─ Stores card (if utoken provided)
   └─ Sends webhook to HighLevel
9. Payment complete
```

---

## API Endpoints Used

### 1. Get Payment Token
**URL**: `POST https://www.paytr.com/odeme/api/get-token`

**Purpose**: Initialize payment and get iframe token

**Request**:
```php
[
    'merchant_id' => '123456',
    'user_ip' => '192.168.1.1',
    'merchant_oid' => 'ORDER_1234567890',
    'email' => 'customer@example.com',
    'payment_amount' => '10000', // in kuruş (100.00 TRY)
    'paytr_token' => base64_encode(hash_hmac(...)),
    'user_basket' => base64_encode('[["Product","100.00",1]]'),
    'debug_on' => '1', // test mode
    'no_installment' => '0',
    'max_installment' => '0',
    'user_name' => 'John Doe',
    'user_address' => 'Address',
    'user_phone' => '5551234567',
    'merchant_ok_url' => 'https://your-domain.com/payments/success',
    'merchant_fail_url' => 'https://your-domain.com/payments/error',
    'timeout_limit' => '30',
    'currency' => 'TL', // Note: TL not TRY
    'test_mode' => '1',
]
```

**Response**:
```json
{
  "status": "success",
  "token": "abc123xyz456",
  "reason": null
}
```

**iframe URL**: `https://www.paytr.com/odeme/guvenli/{token}`

**Implementation**: `PayTRPaymentProvider::initializePayment()` (line 43-196)

---

### 2. Payment Callback
**URL**: `POST https://your-domain.com/payments/callback`

**Purpose**: PayTR sends payment result

**Request** (from PayTR):
```
merchant_oid=ORDER_123&
status=success&
total_amount=10000&
hash=base64_encoded_hash&
payment_id=PT123456&
utoken=user_token&
ctoken=card_token&
card_pan=4355084355084358&
card_type=credit&
failed_reason_code=&
failed_reason_msg=
```

**Hash Verification**:
```php
$calculatedHash = base64_encode(
    hash_hmac('sha256',
        $merchant_oid . $merchant_salt . $status . $total_amount,
        $merchant_key,
        true
    )
);

if ($hash !== $calculatedHash) {
    return 'FAILED';
}
```

**Response**: `OK` (must be exactly this string)

**Implementation**:
- `WebhookController::paytrCallback()`
- `PaymentService::processCallback()`
- `PayTRPaymentProvider::validateCallback()` (line 356-368)

---

### 3. Status Query
**URL**: `POST https://www.paytr.com/odeme/durum-sorgu`

**Purpose**: Query payment status from PayTR

**Request**:
```php
[
    'merchant_id' => '123456',
    'merchant_oid' => 'ORDER_123',
    'paytr_token' => base64_encode(hash_hmac(...))
]
```

**Hash Formula**:
```
hash_str = merchant_oid + merchant_salt
token = base64_encode(hash_hmac('sha256', hash_str, merchant_key, true))
```

**Implementation**: `PayTRPaymentProvider::queryPaymentStatus()` (line 228-252)

---

### 4. Refund
**URL**: `POST https://www.paytr.com/odeme/iade`

**Purpose**: Process refund

**Request**:
```php
[
    'merchant_id' => '123456',
    'merchant_oid' => 'ORDER_123',
    'return_amount' => '5000', // in kuruş
    'paytr_token' => base64_encode(hash_hmac(...))
]
```

**Hash Formula**:
```
hash_str = merchant_oid + return_amount + merchant_salt
token = base64_encode(hash_hmac('sha256', hash_str, merchant_key, true))
```

**Implementation**: `PayTRPaymentProvider::refund()` (line 257-290)

---

## HMAC Signature Generation

### Payment Token Hash

**Order** (MUST match exactly):
```
merchant_id + user_ip + merchant_oid + email +
payment_amount + user_basket + no_installment +
max_installment + currency + test_mode
```

**Then append** `merchant_salt` when calling `hash_hmac`:
```php
$hashStr = $merchant_id . $user_ip . $merchant_oid . $email .
           $payment_amount . $userBasketEncoded . $no_installment .
           $max_installment . $currency . $test_mode;

$token = base64_encode(
    hash_hmac('sha256', $hashStr . $merchant_salt, $merchant_key, true)
);
```

**Implementation**: `PayTRPaymentProvider::initializePayment()` (line 70-73)

### Callback Hash

**Order**:
```
merchant_oid + merchant_salt + status + total_amount
```

```php
$hash = base64_encode(
    hash_hmac('sha256',
        $merchant_oid . $merchant_salt . $status . $total_amount,
        $merchant_key,
        true
    )
);
```

**Implementation**: `PayTRPaymentProvider::validateCallback()` (line 363-365)

---

## Card Storage (utoken/ctoken)

**Flow**:
1. Include `store_card: '1'` in payment initialization
2. PayTR returns `utoken` and `ctoken` in callback
3. Store in `payment_methods` table
4. Use for future charges

**Request** (adding card storage):
```php
$requestData = [
    // ... standard fields
    'store_card' => '1',
    'utoken' => 'optional_existing_token', // if re-using card
];
```

**Callback Response**:
```
utoken=user_abc123&
ctoken=card_xyz456&
card_pan=4355084355084358&
card_type=credit
```

**Storing Card**:
```php
PaymentMethod::create([
    'hl_account_id' => $account->id,
    'location_id' => $locationId,
    'contact_id' => $contactId,
    'provider' => 'paytr',
    'utoken' => $utoken,
    'ctoken' => $ctoken,
    'card_last_four' => substr($card_pan, -4),
    'card_brand' => $this->detectCardBrand($card_pan),
    'card_type' => $card_type,
]);
```

**Implementation**: `PaymentService::storePaymentMethod()` (line 268-292)

---

## User Basket Format

PayTR requires base64-encoded JSON array:

**Structure**:
```php
$basket = [
    ['Product Name', 'Price', Quantity],
    ['Item 2', '50.00', 2],
];

$encoded = base64_encode(json_encode($basket));
```

**Example**:
```php
$basket = [
    ['HighLevel Subscription', '100.00', 1]
];
// Result: W1siSGlnaExldmVsIFN1YnNjcmlwdGlvbiIsIjEwMC4wMCIsMV1d
```

**Implementation**: `PayTRPaymentProvider::prepareUserBasket()` (line 389-405)

---

## Currency Handling

**Important**: PayTR uses `TL` not `TRY` for Turkish Lira

```php
$currency = 'TL'; // NOT 'TRY'
```

**Amount Conversion**:
- Input: Major units (100.00 TRY)
- PayTR expects: Kuruş (10000)

```php
protected function convertToKurus(float $amount): int
{
    return (int) ($amount * 100);
}
```

**Implementation**: `PayTRPaymentProvider::convertToKurus()` (line 381-384)

---

## Test vs. Live Mode

**Test Mode**:
```php
$requestData['test_mode'] = '1';
$requestData['debug_on'] = '1';
```

**Live Mode**:
```php
$requestData['test_mode'] = '0';
$requestData['debug_on'] = '0';
```

**Configuration**: Stored in `hl_accounts.paytr_test_mode`

---

## Error Handling

### Payment Initialization Errors

**Common Errors**:
- `INVALID_HASH` - Hash mismatch (check order of parameters)
- `INVALID_MERCHANT` - Wrong merchant_id
- `INVALID_AMOUNT` - Amount <= 0 or invalid format

**Response**:
```json
{
  "status": "failed",
  "reason": "INVALID_HASH"
}
```

**Implementation**: `PayTRPaymentProvider::initializePayment()` returns error in array

### Callback Errors

**Hash Validation Failure**:
```php
if (!$hashService->validateCallback($callbackData)) {
    Log::error('Invalid PayTR callback hash');
    return 'FAILED';
}
```

**Payment Not Found**:
```php
$payment = Payment::where('merchant_oid', $merchantOid)->first();
if (!$payment) {
    Log::error('Payment not found for callback');
    return false;
}
```

---

## Account-Specific Credentials

**Storage**: `hl_accounts` table (encrypted)
```php
paytr_merchant_id    - Plain text
paytr_merchant_key   - Encrypted
paytr_merchant_salt  - Encrypted
paytr_test_mode      - Boolean
```

**Retrieval**:
```php
$account = HLAccount::where('location_id', $locationId)->first();
$credentials = $account->getPayTRCredentials();

// Returns:
[
    'merchant_id' => '123456',
    'merchant_key' => decrypt($encrypted_key),
    'merchant_salt' => decrypt($encrypted_salt),
    'test_mode' => true
]
```

**Provider Initialization**:
```php
$provider = new PayTRPaymentProvider($account);
// Credentials automatically loaded from account
```

**Implementation**:
- `HLAccount::getPayTRCredentials()` (model method)
- `PayTRPaymentProvider::__construct()` (line 20-38)

---

## Logging

**Payment Initialization**:
```php
Log::info('PayTR payment initialization request', [
    'url' => $apiUrl,
    'requestData' => $requestData,
    'merchant_oid' => $merchantOid,
]);
```

**Callback Processing**:
```php
$this->paymentLogger->logCallback('paytr', $callbackData);
$this->paymentLogger->logPaymentSuccess($payment, $callbackData);
```

**Errors**:
```php
Log::error('PayTR payment initialization failed', [
    'error' => $e->getMessage(),
    'data' => $requestData,
]);
```

---

## Security

**Credential Encryption**:
- `merchant_key` and `merchant_salt` encrypted with Laravel `encrypt()`
- Never exposed in JSON responses (hidden in model)

**Hash Verification**:
- All callbacks verified before processing
- Invalid hash returns `FAILED` response

**HTTPS Required**:
- All PayTR communication over HTTPS
- Callback URL must be HTTPS in production

**IP Whitelist** (optional):
- PayTR provides IP ranges for webhook verification
- Can be implemented in middleware

---

## Testing

**Test Credentials**:
```php
$testAccount = HLAccount::factory()->create([
    'paytr_merchant_id' => env('PAYTR_TEST_MERCHANT_ID'),
    'paytr_merchant_key' => encrypt(env('PAYTR_TEST_KEY')),
    'paytr_merchant_salt' => encrypt(env('PAYTR_TEST_SALT')),
    'paytr_test_mode' => true,
    'paytr_configured' => true,
]);
```

**Test Cards** (from PayTR docs):
```
Success: 4355084355084358
Fail:    5528790000000008
3D:      4506347083970504
```

**Manual Testing**:
```bash
# Create payment
curl "https://your-domain.com/payments/page?\
locationId=test_loc&\
amount=100.00&\
email=test@example.com&\
transactionId=txn_test_001"

# Simulate callback
curl -X POST https://your-domain.com/payments/callback \
  -d "merchant_oid=ORDER_123&status=success&total_amount=10000&hash=..."
```

---

## PayTR API Documentation

**Official Docs**: `technical_documentation/PayTR Direkt API/`

**Key Files**:
- `PayTR Direkt API - Adım 1.pdf` - Token generation
- `PayTR Direkt API - Adım 2.pdf` - Callback handling
- `PayTR Kart Saklama API.pdf` - Card storage
- `PayTR İade API.pdf` - Refunds

---

## Known Limitations

1. **No Direct Card List API**: PayTR doesn't provide API to list saved cards, we maintain our own database
2. **No Card Delete API**: Cards can only be removed from our database, not from PayTR
3. **Callback Required**: Payment status is only known via callback, status query is secondary
4. **Currency Limitation**: Officially supports TRY, USD, EUR (we use TRY only)

---

## Implementation Checklist

- [x] Payment token generation
- [x] iframe integration
- [x] Callback verification
- [x] Card storage (utoken/ctoken)
- [x] Refund processing
- [x] Status query
- [x] Error handling
- [x] Logging
- [x] Test mode support
- [x] Account-specific credentials
- [ ] Installment support (basic implementation, needs testing)
- [ ] 3D Secure handling (partially implemented)

---

## See Also

- [api-endpoints.md](api-endpoints.md) - API endpoint documentation
- [database-schema.md](database-schema.md) - Database structure
- [../SOPs/paytr-hash-generation.md](../SOPs/paytr-hash-generation.md) - Step-by-step hash guide
