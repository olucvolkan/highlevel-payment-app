# API Endpoints

> **Routes**: Defined in `routes/web.php` and `routes/api.php`
> **Base URL**: `https://your-domain.com` or `https://ngrok-url.ngrok.io` (local)

## Endpoint Categories

| Category | Prefix | Description |
|----------|--------|-------------|
| **Web Routes** | `/` | Browser-accessible pages (OAuth, PayTR setup, payment iframe) |
| **API Routes** | `/api/` | JSON API endpoints (payment operations, webhooks) |

---

## Web Routes (`routes/web.php`)

### Landing Page

#### `GET /`
**Controller**: `LandingPageController@index`
**Purpose**: Public landing page for HighLevel Marketplace
**Auth**: None

**Response**: HTML page (Blade view)

**Usage**:
```bash
curl https://your-domain.com/
```

---

### OAuth Routes

#### `GET /oauth/authorize`
**Controller**: `OAuthController@authorize`
**Purpose**: Initiate HighLevel OAuth flow
**Auth**: None

**Query Parameters**:
```
companyId    - HighLevel company ID (from marketplace)
userId       - Installing user ID
```

**Flow**:
1. Redirects to HighLevel OAuth URL
2. User authorizes app
3. HighLevel redirects back to `/oauth/callback`

**Example**:
```bash
curl -L "https://your-domain.com/oauth/authorize?companyId=comp_123&userId=user_456"
```

---

#### `GET /oauth/callback`
**Controller**: `OAuthController@callback`
**Purpose**: Handle OAuth callback from HighLevel
**Auth**: None (public callback)

**Query Parameters**:
```
code         - OAuth authorization code (from HighLevel)
locationId   - HighLevel location ID
companyId    - Company ID
userId       - User ID
```

**Flow**:
1. Exchanges code for access token
2. Stores credentials in `hl_accounts` table
3. Creates HighLevel payment integration
4. Redirects to `/paytr/setup` (if not configured) OR `/oauth/success`

**Example**:
```bash
# This is called by HighLevel, not manually
curl "https://your-domain.com/oauth/callback?code=AUTH_CODE&locationId=loc_123"
```

---

#### `GET /oauth/success`
**Controller**: `OAuthController@success`
**Purpose**: OAuth success page
**Response**: HTML

---

#### `GET /oauth/error`
**Controller**: `OAuthController@error`
**Purpose**: OAuth error page
**Response**: HTML

---

#### `POST /oauth/uninstall`
**Controller**: `OAuthController@uninstall`
**Purpose**: Handle app uninstall
**Auth**: HighLevel webhook signature

**Request Body**:
```json
{
  "locationId": "loc_123",
  "companyId": "comp_456"
}
```

**Response**:
```json
{
  "success": true,
  "message": "App uninstalled successfully"
}
```

---

### Payment Pages

#### `GET /payments/page`
**Controller**: `PaymentController@paymentPage`
**Purpose**: Payment iframe page (embedded in HighLevel)
**Auth**: `X-Location-Id` header OR `locationId` query param

**Query Parameters**:
```
locationId       - Required
transactionId    - Required (HighLevel transaction ID)
amount           - Required (in major units, e.g., 100.00)
currency         - Optional (default: TRY)
email            - Required
contactId        - Optional
```

**Response**: HTML with PayTR iframe

**Example**:
```bash
curl "https://your-domain.com/payments/page?\
locationId=test_loc_123&\
transactionId=txn_001&\
amount=100.00&\
currency=TRY&\
email=customer@example.com"
```

**Blade View**: `resources/views/payments/iframe.blade.php`

**Flow**:
1. Validates account and credentials
2. Creates `Payment` record
3. Calls PayTR API to get iframe token
4. Renders iframe with PayTR payment page
5. Sends `custom_provider_ready` postMessage to parent

---

#### `GET /payments/success`
**Controller**: `PaymentController@success`
**Purpose**: Payment success redirect page
**Query**: `merchant_oid` (optional)
**Response**: HTML success page

---

#### `GET /payments/error`
**Controller**: `PaymentController@error`
**Purpose**: Payment error redirect page
**Query**: `merchant_oid`, `error` (optional)
**Response**: HTML error page

---

#### `GET|POST /payments/callback`
**Controller**: `PaymentController@callback`
**Purpose**: PayTR callback endpoint
**Auth**: Hash verification (HMAC-SHA256)

**Request Body** (from PayTR):
```
merchant_oid         - Our order ID
status               - success | failed
total_amount         - Amount in kuruş (cents)
hash                 - HMAC signature
payment_id           - PayTR payment ID
failed_reason_code   - Error code (if failed)
failed_reason_msg    - Error message (if failed)
utoken               - Card token (if card storage enabled)
ctoken               - Customer token
card_pan             - Card number (masked)
card_type            - credit | debit
```

**Response**:
```
OK       - If hash valid and processed
FAILED   - If hash invalid
ERROR    - If exception occurred
```

**Example**:
```bash
# This is called by PayTR, not manually
curl -X POST https://your-domain.com/payments/callback \
  -d "merchant_oid=ORDER123&status=success&total_amount=10000&hash=ABC..."
```

---

### PayTR Setup Routes

#### `GET /paytr/setup`
**Controller**: `PayTRSetupController@showSetup`
**Purpose**: PayTR credentials configuration page
**Query**: `location_id` (required)

**Response**: HTML form

**Example**:
```bash
curl "https://your-domain.com/paytr/setup?location_id=test_loc_123"
```

---

#### `POST /paytr/credentials`
**Controller**: `PayTRSetupController@saveCredentials`
**Purpose**: Save PayTR merchant credentials
**Auth**: CSRF token

**Request Body**:
```json
{
  "location_id": "test_loc_123",
  "merchant_id": "123456",
  "merchant_key": "abc123xyz",
  "merchant_salt": "salt456",
  "test_mode": true
}
```

**Response**:
```json
{
  "success": true,
  "message": "Configuration saved successfully"
}
```

---

#### `POST /paytr/test`
**Controller**: `PayTRSetupController@testCredentials`
**Purpose**: Test PayTR credentials before saving
**Auth**: CSRF token

**Request Body**: Same as `/paytr/credentials`

**Response**:
```json
{
  "success": true,
  "message": "Credentials are valid"
}
// OR
{
  "success": false,
  "message": "Invalid credentials",
  "details": "Hash mismatch"
}
```

---

#### `GET /paytr/config`
**Controller**: `PayTRSetupController@showConfiguration`
**Purpose**: View current PayTR configuration
**Query**: `location_id`

**Response**:
```json
{
  "configured": true,
  "merchant_id": "123456",
  "test_mode": true,
  "configured_at": "2025-11-22T10:00:00Z"
}
```

---

#### `DELETE /paytr/config`
**Controller**: `PayTRSetupController@removeConfiguration`
**Purpose**: Remove PayTR configuration
**Auth**: CSRF token

**Request Body**:
```json
{
  "location_id": "test_loc_123"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Configuration removed"
}
```

---

### Documentation Route

#### `GET /docs`
**Purpose**: API documentation overview
**Response**: JSON with endpoint list

**Example Response**:
```json
{
  "service": "HighLevel PayTR Integration API",
  "version": "1.0.0",
  "endpoints": {
    "health_check": "https://your-domain.com/api/health",
    "payment_query": "https://your-domain.com/api/payments/query",
    "payment_page": "https://your-domain.com/payments/page"
  },
  "integration_urls": {
    "query_url": "https://your-domain.com/api/payments/query",
    "payments_url": "https://your-domain.com/payments/page",
    "redirect_uri": "https://your-domain.com/oauth/callback",
    "webhook_url": "https://your-domain.com/api/webhooks/marketplace"
  }
}
```

---

## API Routes (`routes/api.php`)

### Payment Operations

#### `POST /api/payments/query`
**Controller**: `PaymentController@query`
**Purpose**: HighLevel payment query endpoint (main integration point)
**Auth**: `X-Location-Id` header OR `locationId` in body

**Request Types**:

##### 1. Verify Payment
```json
{
  "type": "verify",
  "locationId": "test_loc_123",
  "transactionId": "txn_001",
  "chargeId": "chrg_123"
}
```

**Response**:
```json
{
  "success": true,
  "chargeId": "chrg_123",
  "transactionId": "txn_001",
  "amount": 10000,
  "currency": "TRY",
  "status": "success",
  "paidAt": "2025-11-22T10:00:00Z"
}
```

---

##### 2. List Payment Methods
```json
{
  "type": "list_payment_methods",
  "locationId": "test_loc_123",
  "contactId": "cont_123",
  "utoken": "optional_user_token"
}
```

**Response**:
```json
{
  "paymentMethods": [
    {
      "id": "pm_123",
      "type": "card",
      "title": "visa",
      "subTitle": "**** 5454",
      "expiry": "12/2026",
      "imageUrl": "https://cdn.paytr.com/images/visa.png"
    }
  ]
}
```

---

##### 3. Charge Payment
```json
{
  "type": "charge_payment",
  "locationId": "test_loc_123",
  "amount": 100.00,
  "paymentMethodId": "pm_123",
  "transactionId": "txn_002",
  "contactId": "cont_123",
  "currency": "TRY",
  "email": "customer@example.com"
}
```

**Response**:
```json
{
  "success": true,
  "chargeId": "chrg_456"
}
```

---

##### 4. Create Subscription
```json
{
  "type": "create_subscription",
  "locationId": "test_loc_123",
  "amount": 50.00,
  "interval": "monthly",
  "paymentMethodId": "pm_123"
}
```

**Response**:
```json
{
  "error": "Subscriptions not yet implemented"
}
```
**Status**: 501 Not Implemented

---

##### 5. Refund Payment
```json
{
  "type": "refund",
  "locationId": "test_loc_123",
  "chargeId": "chrg_123",
  "amount": 50.00
}
```

**Response**:
```json
{
  "success": true
}
```

---

#### `POST /api/payments/status`
**Controller**: `PaymentController@status`
**Purpose**: Poll payment status (used by iframe for real-time updates)
**Auth**: None (polling endpoint)

**Request Body**:
```json
{
  "merchantOid": "ORDER123",
  "transactionId": "txn_001"
}
```

**Response**:
```json
{
  "status": "success",
  "chargeId": "ORDER123",
  "transactionId": "txn_001",
  "amount": 100.00,
  "currency": "TRY",
  "paidAt": "2025-11-22T10:00:00Z"
}
```

**Status Values**: `pending`, `success`, `failed`, `not_found`

---

### Webhook Endpoints

#### `POST /api/callbacks/paytr`
**Controller**: `WebhookController@paytrCallback`
**Purpose**: PayTR payment callback
**Auth**: Hash verification

**Request Body**: See `/payments/callback` above (same)

**Response**: `OK` | `FAILED`

---

#### `POST /api/webhooks/marketplace`
**Controller**: `WebhookController@marketplaceWebhook`
**Purpose**: HighLevel marketplace events (install/uninstall)
**Auth**: HighLevel signature

**Event Types**:

##### `app.install`
```json
{
  "type": "app.install",
  "locationId": "loc_123",
  "companyId": "comp_456",
  "userId": "user_789"
}
```

##### `app.uninstall`
```json
{
  "type": "app.uninstall",
  "locationId": "loc_123"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Webhook processed"
}
```

---

#### `POST /api/webhooks/highlevel`
**Controller**: `WebhookController@highlevelPaymentWebhook`
**Purpose**: HighLevel payment events
**Auth**: HighLevel signature

**Event Types**:
- `payment.created`
- `payment.updated`
- `subscription.created`
- `subscription.cancelled`

---

### Health & Status

#### `GET /api/health`
**Purpose**: Health check
**Response**:
```json
{
  "status": "ok",
  "service": "HighLevel PayTR Integration",
  "timestamp": "2025-11-22T10:00:00Z",
  "version": "1.0.0"
}
```

---

#### `GET /api/status`
**Purpose**: System status check
**Response**:
```json
{
  "status": "active",
  "providers": {
    "paytr": {
      "api_configured": true,
      "note": "Credentials stored per-location in database"
    }
  },
  "highlevel": {
    "client_configured": true
  },
  "database": {
    "connected": true
  },
  "timestamp": "2025-11-22T10:00:00Z"
}
```

---

## Authentication

### Location-Based Auth
Most endpoints require `locationId` to identify the tenant:

**Option 1**: Header
```
X-Location-Id: test_loc_123
```

**Option 2**: Query Parameter
```
?locationId=test_loc_123
```

**Option 3**: Request Body
```json
{
  "locationId": "test_loc_123",
  ...
}
```

### CSRF Protection
Web routes (POST/DELETE) require CSRF token:
```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```

API routes (`/api/*`) are exempt from CSRF protection.

---

## Error Responses

Standard error format:
```json
{
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": "Additional details"
}
```

**HTTP Status Codes**:
- `200` - Success
- `400` - Bad Request (validation error)
- `401` - Unauthorized (invalid location_id or credentials)
- `404` - Not Found
- `500` - Internal Server Error
- `501` - Not Implemented (subscriptions)

---

## Testing with cURL

### Test Payment Query (Verify)
```bash
curl -X POST https://your-domain.com/api/payments/query \
  -H "Content-Type: application/json" \
  -H "X-Location-Id: test_loc_123" \
  -d '{
    "type": "verify",
    "transactionId": "txn_001",
    "chargeId": "chrg_123"
  }'
```

### Test PayTR Setup
```bash
curl -X POST https://your-domain.com/paytr/credentials \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: your_token" \
  -d '{
    "location_id": "test_loc_123",
    "merchant_id": "123456",
    "merchant_key": "abc123",
    "merchant_salt": "salt456",
    "test_mode": true
  }'
```

---

## Postman Collection

Import `postman_collection.json` from root for pre-configured requests.

Variables needed:
- `BASE_URL` - Your ngrok or production URL
- `LOCATION_ID` - Test location ID
- `CSRF_TOKEN` - For web routes

---

## HighLevel Integration URLs

When configuring in HighLevel Marketplace:

| Setting | URL |
|---------|-----|
| **Query URL** | `https://your-domain.com/api/payments/query` |
| **Payments URL** | `https://your-domain.com/payments/page` |
| **Redirect URI** | `https://your-domain.com/oauth/callback` |
| **Webhook URL** | `https://your-domain.com/api/webhooks/marketplace` |

---

## Rate Limiting

**Status**: Not implemented yet

**Planned**:
- 60 requests/minute for payment endpoints
- 100 requests/minute for query endpoint
- No limit for callbacks (trusted sources)

---

## Notes

- All amounts in major currency units (e.g., `100.00` for 100 TRY)
- PayTR expects amounts in kuruş (multiply by 100 internally)
- Timestamps are ISO 8601 format
- All POST bodies expect `application/json` content-type
- PayTR callback endpoint also accepts GET for testing
