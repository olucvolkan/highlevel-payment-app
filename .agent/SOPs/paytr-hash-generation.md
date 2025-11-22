# SOP: PayTR Hash Generation

> **Purpose**: Generate HMAC-SHA256 signatures for PayTR API calls
> **Use Case**: Payment initialization, callback verification, refunds, status queries

## Payment Initialization Hash

### Step 1: Prepare Variables
```php
$merchant_id = '123456';
$user_ip = '192.168.1.1';
$merchant_oid = 'ORDER_1234567890';
$email = 'customer@example.com';
$payment_amount = '10000'; // in kuruş
$user_basket = base64_encode('[["Product","100.00",1]]');
$no_installment = '0';
$max_installment = '0';
$currency = 'TL'; // NOT TRY!
$test_mode = '1'; // or '0'
```

### Step 2: Concatenate in Exact Order
```php
$hash_str = $merchant_id . $user_ip . $merchant_oid . $email .
            $payment_amount . $user_basket . $no_installment .
            $max_installment . $currency . $test_mode;
```

**Order Matters!** Must match exactly:
1. merchant_id
2. user_ip
3. merchant_oid
4. email
5. payment_amount
6. user_basket (base64 encoded)
7. no_installment
8. max_installment
9. currency
10. test_mode

### Step 3: Add Salt and Hash
```php
$merchant_key = 'your_merchant_key';
$merchant_salt = 'your_merchant_salt';

$paytr_token = base64_encode(
    hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true)
);
```

**Important**:
- Append `$merchant_salt` to `$hash_str` BEFORE hashing
- Use `$merchant_key` as the key for `hash_hmac`
- Set `true` as fourth parameter for binary output
- base64_encode the result

### Complete Example
```php
$merchant_id = '123456';
$merchant_key = 'abc123xyz';
$merchant_salt = 'salt456';
$user_ip = '192.168.1.1';
$merchant_oid = 'ORDER_001';
$email = 'test@example.com';
$payment_amount = '10000';
$user_basket = base64_encode('[["Product","100.00",1]]');
$no_installment = '0';
$max_installment = '0';
$currency = 'TL';
$test_mode = '1';

$hash_str = $merchant_id . $user_ip . $merchant_oid . $email .
            $payment_amount . $user_basket . $no_installment .
            $max_installment . $currency . $test_mode;

$paytr_token = base64_encode(
    hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true)
);

echo $paytr_token;
```

---

## Callback Verification Hash

### Step 1: Extract from Callback
```php
$merchant_oid = $_POST['merchant_oid'];
$status = $_POST['status']; // success | failed
$total_amount = $_POST['total_amount'];
$hash = $_POST['hash']; // Hash sent by PayTR
```

### Step 2: Calculate Expected Hash
```php
$merchant_salt = 'your_salt';
$merchant_key = 'your_key';

$expected_hash = base64_encode(
    hash_hmac('sha256',
        $merchant_oid . $merchant_salt . $status . $total_amount,
        $merchant_key,
        true
    )
);
```

**Order**:
1. merchant_oid
2. merchant_salt
3. status
4. total_amount

### Step 3: Verify
```php
if ($hash === $expected_hash) {
    echo "OK"; // Must respond with exactly "OK"
} else {
    echo "FAILED";
    exit;
}
```

---

## Refund Hash

### Step 1: Prepare Variables
```php
$merchant_oid = 'ORDER_123';
$return_amount = '5000'; // in kuruş
```

### Step 2: Generate Hash
```php
$hash_str = $merchant_oid . $return_amount . $merchant_salt;

$paytr_token = base64_encode(
    hash_hmac('sha256', $hash_str, $merchant_key, true)
);
```

**Order**:
1. merchant_oid
2. return_amount
3. merchant_salt

---

## Status Query Hash

### Step 1: Prepare
```php
$merchant_oid = 'ORDER_123';
```

### Step 2: Generate
```php
$hash_str = $merchant_oid . $merchant_salt;

$paytr_token = base64_encode(
    hash_hmac('sha256', $hash_str, $merchant_key, true)
);
```

**Order**:
1. merchant_oid
2. merchant_salt

---

## Common Mistakes

### ❌ Wrong Order
```php
// WRONG
$hash_str = $email . $merchant_id . $merchant_oid . ...
```
Order must match PayTR documentation exactly.

### ❌ Missing Salt Append
```php
// WRONG
hash_hmac('sha256', $hash_str, $merchant_key, true)

// CORRECT
hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true)
```

### ❌ Not Using Binary Output
```php
// WRONG
hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key)

// CORRECT
hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true)
```

### ❌ Not Base64 Encoding
```php
// WRONG
$token = hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true);

// CORRECT
$token = base64_encode(
    hash_hmac('sha256', $hash_str . $merchant_salt, $merchant_key, true)
);
```

### ❌ Using TRY Instead of TL
```php
// WRONG
$currency = 'TRY';

// CORRECT
$currency = 'TL';
```

---

## Debugging Hash Issues

### 1. Log All Variables
```php
Log::info('PayTR Hash Generation', [
    'merchant_id' => $merchant_id,
    'user_ip' => $user_ip,
    'merchant_oid' => $merchant_oid,
    'email' => $email,
    'payment_amount' => $payment_amount,
    'user_basket' => $user_basket,
    'no_installment' => $no_installment,
    'max_installment' => $max_installment,
    'currency' => $currency,
    'test_mode' => $test_mode,
    'hash_str_length' => strlen($hash_str),
    'generated_token' => $paytr_token,
]);
```

### 2. Check Variable Types
All variables must be strings:
```php
$payment_amount = (string) $payment_amount;
$no_installment = (string) $no_installment;
```

### 3. Verify Credentials
```php
echo "Merchant ID length: " . strlen($merchant_id) . "\n";
echo "Merchant Key length: " . strlen($merchant_key) . "\n";
echo "Merchant Salt length: " . strlen($merchant_salt) . "\n";
```

### 4. Test with PayTR's Example
Use known values from PayTR documentation and verify output matches.

---

## Implementation Reference

**File**: `app/PaymentGateways/PayTRPaymentProvider.php`

**Method**: `initializePayment()` (lines 43-196)

**Service**: `app/Services/PayTRHashService.php`
