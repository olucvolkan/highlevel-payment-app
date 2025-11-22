# SOP: HighLevel OAuth Flow

> **Purpose**: Authenticate and connect HighLevel locations
> **Trigger**: User installs app from HighLevel Marketplace

## Complete Flow

```
User clicks "Install" in HighLevel Marketplace
    ↓
HighLevel → GET /oauth/authorize?companyId=X&userId=Y
    ↓
Our app redirects to HighLevel OAuth page
    ↓
User authorizes app
    ↓
HighLevel → GET /oauth/callback?code=ABC&locationId=X
    ↓
Our app exchanges code for tokens
    ↓
Tokens stored in hl_accounts table
    ↓
Payment integration created
    ↓
User redirected to /paytr/setup or /oauth/success
```

---

## Step 1: Initiate OAuth

**Endpoint**: `GET /oauth/authorize`

**Query Parameters**:
- `companyId` - Required
- `userId` - Required

**Implementation**:
```php
public function authorize(Request $request)
{
    $companyId = $request->get('companyId');
    $userId = $request->get('userId');

    $params = [
        'client_id' => config('services.highlevel.client_id'),
        'redirect_uri' => config('services.highlevel.redirect_uri'),
        'response_type' => 'code',
        'scope' => implode(' ', config('services.highlevel.scopes')),
        'state' => base64_encode(json_encode([
            'company_id' => $companyId,
            'user_id' => $userId,
        ])),
    ];

    $authUrl = config('services.highlevel.oauth_url') . '?' . http_build_query($params);

    return redirect($authUrl);
}
```

**HighLevel OAuth URL**:
```
https://marketplace.gohighlevel.com/oauth/chooselocation?
  client_id=YOUR_CLIENT_ID&
  redirect_uri=https://your-domain.com/oauth/callback&
  response_type=code&
  scope=payments/orders.write payments/custom-provider.write&
  state=eyJjb21wYW55X2lkIjoiY29tcF8xMjMifQ==
```

---

## Step 2: Handle Callback

**Endpoint**: `GET /oauth/callback`

**Query Parameters**:
- `code` - Authorization code (from HighLevel)
- `locationId` - Selected location
- `companyId` - Company ID (from state)
- `userId` - User ID (from state)

**Implementation**:
```php
public function callback(Request $request)
{
    $code = $request->get('code');
    $locationId = $request->get('locationId');

    // 1. Exchange code for tokens
    $tokens = $this->highLevelService->exchangeCodeForToken($code);

    // 2. Store in database
    $account = HLAccount::updateOrCreate(
        ['location_id' => $locationId],
        [
            'company_id' => $tokens['companyId'],
            'user_id' => $tokens['userId'],
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'scopes' => $tokens['scope'],
            'is_active' => true,
        ]
    );

    // 3. Create payment integration
    $this->highLevelService->createPaymentIntegration($account);

    // 4. Redirect based on PayTR config
    if (!$account->hasPayTRCredentials()) {
        return redirect()->route('paytr.setup', ['location_id' => $locationId]);
    }

    return redirect()->route('oauth.success');
}
```

---

## Step 3: Token Exchange

**API Endpoint**: `POST https://services.leadconnectorhq.com/oauth/token`

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
  "access_token": "eyJhbGc...",
  "refresh_token": "rt_abc123...",
  "token_type": "Bearer",
  "expires_in": 86400,
  "scope": "payments/orders.write payments/custom-provider.write...",
  "userId": "user_123",
  "companyId": "comp_456",
  "locationId": "loc_789"
}
```

**cURL Example**:
```bash
curl -X POST https://services.leadconnectorhq.com/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "YOUR_CLIENT_ID",
    "client_secret": "YOUR_CLIENT_SECRET",
    "grant_type": "authorization_code",
    "code": "AUTH_CODE",
    "redirect_uri": "https://your-domain.com/oauth/callback"
  }'
```

---

## Step 4: Create Payment Integration

**API Endpoint**: `POST https://services.leadconnectorhq.com/payments/custom-provider/connect`

**Headers**:
```
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Request**:
```json
{
  "liveMode": false,
  "name": "PayTR",
  "queryUrl": "https://your-domain.com/api/payments/query",
  "paymentsUrl": "https://your-domain.com/payments/page",
  "disconnectUrl": "https://your-domain.com/oauth/uninstall"
}
```

**Response**:
```json
{
  "integration": {
    "id": "integration_123",
    "name": "PayTR",
    "status": "active",
    "queryUrl": "https://your-domain.com/api/payments/query",
    "paymentsUrl": "https://your-domain.com/payments/page"
  }
}
```

**Implementation**:
```php
public function createPaymentIntegration(HLAccount $account)
{
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $account->access_token,
        'Content-Type' => 'application/json',
    ])->post(config('services.highlevel.api_url') . '/payments/custom-provider/connect', [
        'liveMode' => false,
        'name' => 'PayTR',
        'queryUrl' => config('app.url') . '/api/payments/query',
        'paymentsUrl' => config('app.url') . '/payments/page',
        'disconnectUrl' => config('app.url') . '/oauth/uninstall',
    ]);

    if ($response->successful()) {
        $account->update([
            'integration_id' => $response->json('integration.id'),
        ]);
    }

    return $response->json();
}
```

---

## Step 5: Token Refresh

**When**: Before token expires (`token_expires_at`)

**API Endpoint**: `POST https://services.leadconnectorhq.com/oauth/token`

**Request**:
```php
[
    'client_id' => env('HIGHLEVEL_CLIENT_ID'),
    'client_secret' => env('HIGHLEVEL_CLIENT_SECRET'),
    'grant_type' => 'refresh_token',
    'refresh_token' => $account->refresh_token,
]
```

**Response**: Same as token exchange

**Implementation**:
```php
public function refreshToken(HLAccount $account)
{
    if ($account->token_expires_at > now()->addMinutes(5)) {
        return; // Token still valid
    }

    $response = Http::asForm()->post(
        config('services.highlevel.oauth_url') . '/token',
        [
            'client_id' => config('services.highlevel.client_id'),
            'client_secret' => config('services.highlevel.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]
    );

    if ($response->successful()) {
        $data = $response->json();

        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);
    }
}
```

---

## Error Handling

### Invalid Code
```php
if (!$code) {
    return redirect()->route('oauth.error')
        ->with('error', 'Authorization code missing');
}
```

### Token Exchange Failure
```php
try {
    $tokens = $this->highLevelService->exchangeCodeForToken($code);
} catch (\Exception $e) {
    Log::error('OAuth token exchange failed', [
        'error' => $e->getMessage(),
        'code' => $code,
    ]);

    return redirect()->route('oauth.error')
        ->with('error', 'Failed to connect to HighLevel');
}
```

### Integration Creation Failure
```php
try {
    $this->highLevelService->createPaymentIntegration($account);
} catch (\Exception $e) {
    Log::error('Payment integration creation failed', [
        'error' => $e->getMessage(),
        'account_id' => $account->id,
    ]);

    // Continue anyway, can be created manually later
}
```

---

## Logging

**OAuth Success**:
```php
$this->userActionLogger->log($account, 'oauth_success', [
    'company_id' => $companyId,
    'user_id' => $userId,
    'scopes' => $scopes,
]);
```

**OAuth Failure**:
```php
$this->userActionLogger->log(null, 'oauth_failed', [
    'error' => $e->getMessage(),
    'code' => $code,
    'location_id' => $locationId,
]);
```

---

## Testing

### Local Testing with ngrok

1. Start ngrok:
```bash
ngrok http 8000
```

2. Update `.env`:
```env
APP_URL=https://abc123.ngrok.io
HIGHLEVEL_REDIRECT_URI=https://abc123.ngrok.io/oauth/callback
```

3. Configure in HighLevel Marketplace:
- Redirect URI: `https://abc123.ngrok.io/oauth/callback`

4. Click "Install" and test flow

### Manual Token Test
```bash
# Get authorization code manually from HighLevel
# Then exchange:
curl -X POST https://services.leadconnectorhq.com/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "YOUR_ID",
    "client_secret": "YOUR_SECRET",
    "grant_type": "authorization_code",
    "code": "AUTH_CODE",
    "redirect_uri": "https://your-domain.com/oauth/callback"
  }'
```

---

## Environment Variables

Required in `.env`:
```env
HIGHLEVEL_CLIENT_ID=your_client_id
HIGHLEVEL_CLIENT_SECRET=your_client_secret
HIGHLEVEL_REDIRECT_URI=https://your-domain.com/oauth/callback
```

---

## Files

- **Controller**: `app/Http/Controllers/OAuthController.php`
- **Service**: `app/Services/HighLevelService.php`
- **Model**: `app/Models/HLAccount.php`
- **Config**: `config/services.php`
