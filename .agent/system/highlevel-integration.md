# HighLevel Integration

> **Platform**: HighLevel CRM
> **Integration Type**: Custom Payment Provider
> **OAuth**: OAuth 2.0
> **API Docs**: https://highlevel.stoplight.io

## OAuth Flow

### 1. Installation
**Triggered by**: User clicks "Install" in HighLevel Marketplace

**Flow**:
```
User clicks Install
    ↓
HighLevel → GET /oauth/authorize?companyId=X&userId=Y
    ↓
OAuthController redirects to HighLevel OAuth
    ↓
User authorizes app
    ↓
HighLevel → GET /oauth/callback?code=ABC&locationId=X
    ↓
OAuthController exchanges code for tokens
    ↓
Store in hl_accounts table
    ↓
Create payment integration via HighLevel API
    ↓
Redirect to /paytr/setup (if not configured)
```

**Implementation**: `OAuthController` (lines 24-143)

### 2. Token Exchange
**Endpoint**: `POST https://services.leadconnectorhq.com/oauth/token`

**Request**:
```php
[
    'client_id' => env('HIGHLEVEL_CLIENT_ID'),
    'client_secret' => env('HIGHLEVEL_CLIENT_SECRET'),
    'grant_type' => 'authorization_code',
    'code' => $authorizationCode,
    'redirect_uri' => config('app.url') . '/oauth/callback',
]
```

**Response**:
```json
{
  "access_token": "eyJ...",
  "refresh_token": "rt_...",
  "token_type": "Bearer",
  "expires_in": 86400,
  "scope": "payments/orders.write payments/custom-provider.write...",
  "userId": "user_123",
  "companyId": "comp_456",
  "locationId": "loc_789"
}
```

**Storage**:
```php
HLAccount::updateOrCreate(
    ['location_id' => $locationId],
    [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_expires_at' => now()->addSeconds($expiresIn),
        'scopes' => $scopes,
        'company_id' => $companyId,
        'user_id' => $userId,
    ]
);
```

### 3. Token Refresh
**When**: Before token expires (`token_expires_at`)

**Request**:
```php
[
    'client_id' => env('HIGHLEVEL_CLIENT_ID'),
    'client_secret' => env('HIGHLEVEL_CLIENT_SECRET'),
    'grant_type' => 'refresh_token',
    'refresh_token' => $account->refresh_token,
]
```

**Implementation**: `HighLevelService::refreshToken()` (line 82-113)

---

## Required Scopes

```
payments/orders.readonly
payments/orders.write
payments/subscriptions.readonly
payments/transactions.readonly
payments/custom-provider.readonly
payments/custom-provider.write
products.readonly
products/prices.readonly
```

**Configuration**: `config/services.php` → `highlevel.scopes`

---

## Payment Integration Creation

**When**: After successful OAuth

**Endpoint**: `POST /payments/custom-provider/connect`

**Request**:
```php
[
    'liveMode' => false,
    'name' => 'PayTR',
    'queryUrl' => config('app.url') . '/api/payments/query',
    'paymentsUrl' => config('app.url') . '/payments/page',
    'disconnectUrl' => config('app.url') . '/oauth/uninstall',
]
```

**Response**:
```json
{
  "integration": {
    "id": "integration_123",
    "name": "PayTR",
    "status": "active"
  }
}
```

**Implementation**: `HighLevelService::createPaymentIntegration()` (line 37-80)

---

## Query URL Operations

**Endpoint**: `POST /api/payments/query`

HighLevel calls this for all payment operations.

### Request Types

| Type | Purpose | Implementation Status |
|------|---------|----------------------|
| `verify` | Verify payment status | ✅ Complete |
| `list_payment_methods` | List saved cards | ✅ Complete |
| `charge_payment` | Charge saved card | ✅ Complete |
| `create_subscription` | Start subscription | ❌ Not implemented (501) |
| `refund` | Process refund | ✅ Complete |

**Implementation**: `PaymentController::query()` (line 32-73)

---

## Webhooks to HighLevel

**Endpoint**: `POST https://backend.leadconnectorhq.com/payments/custom-provider/webhook`

**Authorization**:
```
Authorization: Bearer {access_token}
```

### Event Types

#### `payment.captured`
```php
$this->highLevelService->sendPaymentCaptured($account, [
    'chargeId' => $payment->charge_id,
    'transactionId' => $payment->transaction_id,
    'amount' => (int) ($payment->amount * 100), // in cents
    'chargedAt' => $payment->paid_at->timestamp,
]);
```

**Implementation**: `HighLevelService::sendPaymentCaptured()` (line 115-152)

#### `subscription.active`
```json
{
  "type": "subscription.active",
  "subscriptionId": "sub_123",
  "status": "active",
  "activatedAt": 1700000000
}
```

**Status**: Not implemented (subscriptions not ready)

---

## Marketplace Webhooks

**Endpoint**: `POST /api/webhooks/marketplace`

### `app.install`
```json
{
  "type": "app.install",
  "locationId": "loc_123",
  "companyId": "comp_456",
  "userId": "user_789",
  "timestamp": 1700000000
}
```

**Handler**: `WebhookController::marketplaceWebhook()` (line 75-115)

**Actions**:
- Log event to `webhook_logs`
- Create or update `hl_accounts` record
- Log to `user_activity_logs`

### `app.uninstall`
```json
{
  "type": "app.uninstall",
  "locationId": "loc_123"
}
```

**Actions**:
- Soft delete `hl_accounts` record
- Optionally deactivate related records
- Log uninstall event

---

## postMessage Communication

**Purpose**: iframe ↔ HighLevel parent window communication

### Events We Send

#### `custom_provider_ready`
**When**: iframe loaded and payment initialized
```javascript
window.parent.postMessage({
    type: 'custom_provider_ready',
    data: {
        merchantOid: 'ORDER_123',
        transactionId: 'txn_001'
    }
}, '*');
```

#### `custom_element_success_response`
**When**: Payment successful
```javascript
window.parent.postMessage({
    type: 'custom_element_success_response',
    data: {
        chargeId: 'chrg_123',
        transactionId: 'txn_001',
        amount: 10000,
        currency: 'TRY'
    }
}, '*');
```

#### `custom_element_error_response`
**When**: Payment failed
```javascript
window.parent.postMessage({
    type: 'custom_element_error_response',
    data: {
        error: 'Payment failed',
        transactionId: 'txn_001'
    }
}, '*');
```

#### `custom_element_close_response`
**When**: User closes payment
```javascript
window.parent.postMessage({
    type: 'custom_element_close_response',
    data: {}
}, '*');
```

**Implementation**: `resources/views/payments/iframe.blade.php`

**Status**: ⚠️ Basic implementation, needs enhancement

---

## iframe Security

**X-Frame-Options**: Must allow HighLevel domains

**Allowed Origins**:
```
https://app.gohighlevel.com
https://*.gohighlevel.com
https://backend.leadconnectorhq.com
```

**CORS Configuration**:
```php
'allowed_origins' => [
    'https://app.gohighlevel.com',
    'https://backend.leadconnectorhq.com',
],
```

**Status**: ⚠️ Not explicitly configured yet

---

## Configuration URLs

**For HighLevel Marketplace Setup**:

| Setting | URL |
|---------|-----|
| OAuth Redirect URI | `https://your-domain.com/oauth/callback` |
| Query URL | `https://your-domain.com/api/payments/query` |
| Payments URL | `https://your-domain.com/payments/page` |
| Webhook URL | `https://your-domain.com/api/webhooks/marketplace` |
| Disconnect URL | `https://your-domain.com/oauth/uninstall` |

---

## Environment Variables

```env
# HighLevel OAuth
HIGHLEVEL_CLIENT_ID=your_client_id
HIGHLEVEL_CLIENT_SECRET=your_client_secret
HIGHLEVEL_REDIRECT_URI=https://your-domain.com/oauth/callback

# HighLevel API
HIGHLEVEL_API_URL=https://services.leadconnectorhq.com
HIGHLEVEL_WEBHOOK_URL=https://backend.leadconnectorhq.com/payments/custom-provider/webhook
```

**Configuration File**: `config/services.php`

---

## Error Handling

### OAuth Errors
```php
try {
    $tokens = $this->exchangeCodeForToken($code);
} catch (\Exception $e) {
    Log::error('OAuth token exchange failed', [
        'error' => $e->getMessage(),
        'code' => $code,
    ]);
    return redirect()->route('oauth.error')
        ->with('error', 'Failed to connect to HighLevel');
}
```

### API Call Errors
```php
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $account->access_token,
])->post($url, $data);

if ($response->failed()) {
    Log::error('HighLevel API call failed', [
        'status' => $response->status(),
        'body' => $response->body(),
    ]);
    throw new \Exception('HighLevel API error');
}
```

---

## Testing

### OAuth Flow Test
```bash
# Start OAuth
curl "https://your-domain.com/oauth/authorize?companyId=test_comp&userId=test_user"

# Mock callback (requires valid code from HighLevel)
curl "https://your-domain.com/oauth/callback?code=AUTH_CODE&locationId=test_loc"
```

### Query Endpoint Test
```bash
curl -X POST https://your-domain.com/api/payments/query \
  -H "Content-Type: application/json" \
  -H "X-Location-Id: test_loc_123" \
  -d '{"type": "verify", "transactionId": "txn_001"}'
```

---

## Implementation Files

- **OAuthController**: `app/Http/Controllers/OAuthController.php`
- **HighLevelService**: `app/Services/HighLevelService.php`
- **WebhookController**: `app/Http/Controllers/WebhookController.php`
- **PaymentController**: `app/Http/Controllers/PaymentController.php`

---

## Logging

**OAuth Events**:
```php
$this->userActionLogger->log($account, 'oauth_success', [
    'company_id' => $companyId,
    'user_id' => $userId,
    'scopes' => $scopes,
]);
```

**Webhook Events**:
```php
WebhookLog::create([
    'source' => 'highlevel',
    'event_type' => $eventType,
    'payload' => $requestData,
    'status_code' => 200,
    'processed' => true,
]);
```

---

## Known Issues

1. **postMessage Events**: Basic implementation, needs real-time status polling
2. **Subscriptions**: Not implemented (returns 501)
3. **CORS**: Not explicitly configured for HighLevel domains
4. **Webhook Retry**: No retry mechanism for failed webhooks

---

## See Also

- [api-endpoints.md](api-endpoints.md) - Endpoint documentation
- [../SOPs/oauth-flow.md](../SOPs/oauth-flow.md) - OAuth step-by-step guide
