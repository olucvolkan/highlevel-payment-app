# PayTR Payment Request Examples

## 1. Basic Payment Request (Minimal)

### Endpoint
```
GET/POST http://127.0.0.1:8000/payments/page
```

### Headers
```
X-Location-Id: your-location-id-here
Content-Type: application/x-www-form-urlencoded
```

### Request Parameters (Query String or Form Data)
```
amount=100.00
email=customer@example.com
transactionId=TXN_12345678
locationId=your-location-id-here
```

### cURL Example
```bash
curl -X POST "http://127.0.0.1:8000/payments/page" \
  -H "X-Location-Id: your-location-id-here" \
  -d "amount=100.00" \
  -d "email=customer@example.com" \
  -d "transactionId=TXN_12345678" \
  -d "locationId=your-location-id-here"
```

---

## 2. Complete Payment Request (With All Optional Parameters)

### Request Parameters
```
amount=250.50
currency=TRY
email=volkanoluc@gmail.com
transactionId=TXN_20241115_001
locationId=your-location-id-here
contactId=contact_123456
orderId=ORDER_789012
subscriptionId=SUB_345678
mode=payment
user_name=Volkan Oluç
user_phone=5551234567
user_address=Istanbul, Turkey
installment_count=0
items[0][name]=Product 1
items[0][price]=150.50
items[0][quantity]=1
items[1][name]=Product 2
items[1][price]=100.00
items[1][quantity]=1
metadata[customer_note]=Special order
metadata[source]=web
```

### cURL Example
```bash
curl -X POST "http://127.0.0.1:8000/payments/page" \
  -H "X-Location-Id: your-location-id-here" \
  -d "amount=250.50" \
  -d "currency=TRY" \
  -d "email=volkanoluc@gmail.com" \
  -d "transactionId=TXN_20241115_001" \
  -d "locationId=your-location-id-here" \
  -d "contactId=contact_123456" \
  -d "orderId=ORDER_789012" \
  -d "user_name=Volkan Oluç" \
  -d "user_phone=5551234567" \
  -d "user_address=Istanbul, Turkey" \
  -d "installment_count=0"
```

---

## 3. Payment with Saved Card (Using utoken)

### Request Parameters
```
amount=99.99
email=customer@example.com
transactionId=TXN_SAVED_CARD_001
locationId=your-location-id-here
contactId=contact_123456
utoken=user_token_from_previous_payment
store_card=1
```

### cURL Example
```bash
curl -X POST "http://127.0.0.1:8000/payments/page" \
  -H "X-Location-Id: your-location-id-here" \
  -d "amount=99.99" \
  -d "email=customer@example.com" \
  -d "transactionId=TXN_SAVED_CARD_001" \
  -d "locationId=your-location-id-here" \
  -d "contactId=contact_123456" \
  -d "utoken=previous_utoken_value" \
  -d "store_card=1"
```

---

## 4. Postman Collection Format

### Request 1: Basic Payment
```json
{
  "method": "POST",
  "url": "http://127.0.0.1:8000/payments/page",
  "headers": {
    "X-Location-Id": "your-location-id-here",
    "Content-Type": "application/x-www-form-urlencoded"
  },
  "body": {
    "mode": "urlencoded",
    "urlencoded": [
      {"key": "amount", "value": "100.00"},
      {"key": "email", "value": "customer@example.com"},
      {"key": "transactionId", "value": "TXN_12345678"},
      {"key": "locationId", "value": "your-location-id-here"}
    ]
  }
}
```

---

## 5. JavaScript/Axios Example

```javascript
const axios = require('axios');

// Basic payment
const paymentData = {
  amount: 100.00,
  email: 'customer@example.com',
  transactionId: 'TXN_' + Date.now(),
  locationId: 'your-location-id-here',
  currency: 'TRY',
  user_name: 'John Doe',
  user_phone: '5551234567',
  user_address: 'Istanbul, Turkey'
};

axios.post('http://127.0.0.1:8000/payments/page',
  new URLSearchParams(paymentData).toString(),
  {
    headers: {
      'X-Location-Id': 'your-location-id-here',
      'Content-Type': 'application/x-www-form-urlencoded'
    }
  }
)
.then(response => {
  console.log('Payment page loaded:', response.data);
})
.catch(error => {
  console.error('Payment failed:', error.response.data);
});
```

---

## 6. PHP Example (for testing)

```php
<?php
$locationId = 'your-location-id-here';

$paymentData = [
    'amount' => 100.00,
    'email' => 'customer@example.com',
    'transactionId' => 'TXN_' . time(),
    'locationId' => $locationId,
    'currency' => 'TRY',
    'user_name' => 'Test User',
    'user_phone' => '5551234567',
    'user_address' => 'Test Address',
];

$ch = curl_init('http://127.0.0.1:8000/payments/page');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Location-Id: ' . $locationId,
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
```

---

## Required Parameters

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `amount` | float | Payment amount (e.g., 100.00) | ✅ Yes |
| `email` | string | Customer email | ✅ Yes |
| `transactionId` | string | Unique transaction ID from HighLevel | ✅ Yes |
| `locationId` | string | HighLevel location ID | ✅ Yes (in header or body) |

## Optional Parameters

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `currency` | string | Currency code (automatically set to TL) | TRY |
| `contactId` | string | HighLevel contact ID | null |
| `orderId` | string | HighLevel order ID | null |
| `subscriptionId` | string | HighLevel subscription ID | null |
| `mode` | string | Payment mode (payment/subscription) | payment |
| `user_name` | string | Customer full name | Customer |
| `user_phone` | string | Customer phone number | 0000000000 |
| `user_address` | string | Customer address | N/A |
| `installment_count` | integer | Number of installments (0 for one-time) | 0 |
| `items` | array | Product items array | [] |
| `metadata` | array | Additional metadata | [] |
| `utoken` | string | User token for saved cards | null |
| `store_card` | boolean | Whether to save card (1 or 0) | false |
| `success_url` | string | Custom success redirect URL | /payments/success |
| `fail_url` | string | Custom failure redirect URL | /payments/error |

---

## Response

### Success Response (HTML with iframe)
```html
<!DOCTYPE html>
<html>
<head>
    <title>Payment</title>
</head>
<body>
    <iframe src="https://www.paytr.com/odeme/guvenli/{token}"
            width="100%"
            height="600px">
    </iframe>
</body>
</html>
```

### Error Response (400)
```
Invalid payment parameters
```

### Error Response (401)
```
Invalid account
```

---

## Testing with your current setup

Based on your logs, here's a working example:

```bash
curl -X POST "http://127.0.0.1:8000/payments/page" \
  -H "X-Location-Id: your-location-id-here" \
  -d "amount=100.00" \
  -d "email=volkanoluc@gmail.com" \
  -d "transactionId=TXN_$(date +%s)" \
  -d "locationId=your-location-id-here" \
  -d "user_name=Volkan Oluç" \
  -d "user_phone=5551234567"
```

Make sure you have:
1. A valid `location_id` in your `hl_accounts` table
2. PayTR credentials configured for that location
3. The location_id matches between the header and database
