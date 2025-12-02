# HighLevel Authentication Issue Analysis

## Current Problem

**Error Received:**
```json
{
  "message": "This authClass type is not allowed to access this scope. Please verify your IAM configuration if this is not the case."
}
```

**Endpoint Attempted:**
- `POST https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel`

**Current Authentication Method:**
- Using OAuth access token obtained via authorization code flow
- Token obtained with `user_type: "Company"` (Agency/Company level token)
- Token stored as `$account->access_token` and used as: `Http::withToken($account->access_token)`

---

## Root Cause Analysis

### The Critical Misunderstanding

Your application is attempting to use the **White-Label Payment Provider** endpoint, which is specifically designed for payment gateways **built on top of existing HighLevel-native providers** (NMI or Authorize.net). This endpoint:

1. **Requires a marketplace-app token** (not a standard OAuth location/company token)
2. **Is only for white-labeling NMI/Authorize.net** - not for custom third-party gateways
3. **Is restricted by authClass** - the token type you're using (Company-level OAuth token) does not have permission to access this endpoint

### What You Actually Need

According to the HighLevel documentation, PayTR as a completely custom payment gateway should use the **Third Party Provider** approach, NOT the White-Label Provider approach.

---

## Two Distinct Integration Approaches in HighLevel

### 1. White-Label Payment Provider (What You're Trying)

**Purpose:**
- For payment solutions built **on top of** NMI or Authorize.net
- Allows resellers to white-label HighLevel's existing payment infrastructure
- Creates a branded version of an existing HighLevel-native provider

**Requirements:**
- Marketplace app categorized as "White Label Payment Provider"
- Uses specific API endpoints: `/payments/integrations/provider/whitelabel`
- Requires "marketplace-app token" (NOT standard OAuth token)
- Limited to NMI and Authorize.net as base providers

**Example Use Cases:**
- A payment processor reselling NMI gateway
- An agency white-labeling Authorize.net for clients

**Why PayTR Doesn't Fit:**
- PayTR is a completely independent Turkish payment gateway
- It's NOT built on NMI or Authorize.net
- It has its own API, iframe system, and payment processing

---

### 2. Third Party Provider (What You Should Use)

**Purpose:**
- For completely custom payment gateway integrations
- Supports any payment processor with its own API
- Full control over payment flow and user experience

**Requirements:**
- Marketplace app categorized as "Third Party Provider"
- Uses **Create Public Provider Config API** endpoint (NOT white-label endpoint)
- Uses standard OAuth tokens (Company or Location level)
- Implements custom payment pages, iframe communication, and webhook handlers

**API Endpoint:**
- The documentation references "Create Public provider config API" (not the white-label endpoint)
- Exact endpoint: Research needed - likely `/payments/integrations` or similar

**Workflow:**
1. User installs marketplace app
2. OAuth flow completes, you get access token
3. Call **Create Public Provider Config API** with:
   - `name`: Provider name (e.g., "PayTR")
   - `description`: Provider description
   - `imageUrl`: Provider logo
   - `locationId`: Location ID from OAuth
   - `queryUrl`: Your backend verification endpoint
   - `paymentsUrl`: Your iframe payment page URL
4. Users configure test/live credentials via **Connect Config API**:
   - `apiKey`: Server-side key for backend verification
   - `publishableKey`: Public key for frontend

**Why PayTR DOES Fit:**
- PayTR is a custom, independent gateway
- You control the entire payment flow
- You implement iframe-based payment pages
- Standard OAuth authentication works

---

## Understanding HighLevel Token Types

### 1. Company/Agency Level Token
- **user_type:** `"Company"`
- **authClass:** `"Company"`
- **Access:** Agency-level APIs (create sub-accounts, company settings, etc.)
- **Obtained:** OAuth flow with `user_type: "Company"` parameter
- **Use Case:** Apps that manage agency-wide features

### 2. Location/Sub-Account Level Token
- **user_type:** `"Location"`
- **authClass:** `"Location"`
- **Access:** Location-specific APIs (contacts, payments for a location)
- **Obtained:** OAuth flow with `user_type: "Location"` parameter
- **Use Case:** Apps that work within a single sub-account

### 3. Marketplace-App Token
- **Purpose:** Special token for specific marketplace operations
- **Access:** White-label provider management endpoints
- **Obtained:** Unknown - documentation doesn't clearly explain this
- **authClass:** Likely a special class like "App" or "Marketplace"
- **Use Case:** White-label provider registration (NMI/Authorize.net only)

### What authClass Error Means

The error "This authClass type is not allowed to access this scope" means:
- Your token has `authClass: "Company"` (from Company-level OAuth)
- The white-label endpoint requires a different authClass (likely "Marketplace" or "App")
- HighLevel's IAM system is blocking your request because the token type doesn't match

---

## The Correct Implementation Path

### Step 1: Change Marketplace App Category

In your HighLevel marketplace app settings:
- **Current (Wrong):** "White Label Payment Provider"
- **Correct:** "Third Party Provider"

This ensures your app appears correctly in the marketplace and on the Payments > Integrations page.

### Step 2: Use the Correct API Endpoint

**REMOVE:**
```php
POST https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel
```

**REPLACE WITH:**
```php
POST https://services.leadconnectorhq.com/payments/custom-provider/provider
```

This is the "Create New Integration" API that creates an association between your marketplace app and the location.

### Step 3: Update API Call Payload

The `/payments/custom-provider/provider` endpoint creates an association between the marketplace app and the location. Based on the documentation, the payload structure should include:

```php
// Step 1: Create the provider association
$payload = [
    'name' => 'PayTR',
    'description' => 'PayTR Payment Gateway for Turkey',
    'imageUrl' => 'https://your-domain.com/paytr-logo.png',
    'locationId' => $account->location_id,
    'queryUrl' => config('app.url') . '/api/payments/query',
    'paymentsUrl' => config('app.url') . '/payments/page',
    // Add any other required fields based on API documentation
];

$response = Http::withToken($account->access_token)
    ->withHeaders([
        'Version' => '2021-07-28',
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->post('https://services.leadconnectorhq.com/payments/custom-provider/provider', $payload);

// Step 2: After successful integration, configure credentials via Connect API
// This is done when users input their PayTR credentials in your config page
$configPayload = [
    'locationId' => $account->location_id,
    'live' => [
        'apiKey' => 'your-server-side-key',
        'publishableKey' => 'your-public-key'
    ],
    'test' => [
        'apiKey' => 'your-test-server-key',
        'publishableKey' => 'your-test-public-key'
    ],
];

$configResponse = Http::withToken($account->access_token)
    ->withHeaders([
        'Version' => '2021-07-28',
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->post('https://services.leadconnectorhq.com/payments/custom-provider/connect', $configPayload);
```

### Step 4: Implement Required Components

1. **Query URL Endpoint** (`/api/payments/query`):
   - Handles POST requests from HighLevel
   - Supports actions: `verify`, `list_payment_methods`, `charge_payment`, `create_subscription`, `refund`
   - Uses `apiKey` parameter from HighLevel for authentication

2. **Payments URL (Iframe Page)** (`/payments/page`):
   - HTTPS page loaded in iframe
   - Implements postMessage communication
   - Dispatches events: `custom_provider_ready`, `custom_element_success_response`, etc.

3. **Connect Config API Calls**:
   - When users configure test/live credentials
   - Sends `apiKey` and `publishableKey` to HighLevel
   - Enables payment processing in each mode

---

## Required Code Changes

### File: `/Users/volkanoluc/Projects/highlevel-paytr-integration/app/Services/HighLevelService.php`

**Location:** Lines 366-372

**Current (WRONG):**
```php
$response = Http::withToken($account->access_token)
    ->withHeaders([
        'Version' => self::API_VERSION,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->post('https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel', $payload);
```

**Replace With:**
```php
$response = Http::withToken($account->access_token)
    ->withHeaders([
        'Version' => self::API_VERSION,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->post('https://services.leadconnectorhq.com/payments/custom-provider/provider', $payload);
```

**Update Method Name:**
```php
// Change from:
public function createWhiteLabelProvider(HLAccount $account, array $config): array

// To:
public function createPublicProviderConfig(HLAccount $account, array $config): array
```

**Update Payload Structure:**
```php
$payload = [
    'name' => $config['title'], // Not 'uniqueName'
    'description' => $config['description'],
    'imageUrl' => $config['imageUrl'],
    'locationId' => $account->location_id,
    'queryUrl' => config('app.url') . '/api/payments/query',
    'paymentsUrl' => config('app.url') . '/payments/page',
];
```

### File: `/Users/volkanoluc/Projects/highlevel-paytr-integration/app/Http/Controllers/OAuthController.php`

**Location:** Lines 70-77

**Current:**
```php
$whitelabelResult = $this->highLevelService->createWhiteLabelProvider($account, [
    'uniqueName' => config('services.highlevel.whitelabel.unique_name'),
    'title' => config('services.highlevel.whitelabel.title'),
    'provider' => config('services.highlevel.whitelabel.provider'),
    'description' => config('services.highlevel.whitelabel.description'),
    'imageUrl' => config('services.highlevel.whitelabel.image_url'),
]);
```

**Replace With:**
```php
$providerConfigResult = $this->highLevelService->createPublicProviderConfig($account, [
    'title' => config('services.highlevel.provider.title'),
    'description' => config('services.highlevel.provider.description'),
    'imageUrl' => config('services.highlevel.provider.image_url'),
]);
```

### File: `/Users/volkanoluc/Projects/highlevel-paytr-integration/config/services.php`

**Update Configuration Section:**

```php
'highlevel' => [
    'client_id' => env('HIGHLEVEL_CLIENT_ID'),
    'client_secret' => env('HIGHLEVEL_CLIENT_SECRET'),
    'sso_key' => env('HIGHLEVEL_SSO_KEY'),
    'redirect_uri' => env('HIGHLEVEL_REDIRECT_URI'),
    'webhook_url' => env('HIGHLEVEL_WEBHOOK_URL'),
    'api_url' => env('HIGHLEVEL_API_URL', 'https://backend.leadconnectorhq.com'),
    'oauth_url' => env('HIGHLEVEL_OAUTH_URL', 'https://services.leadconnectorhq.com'),

    // Third-party payment provider configuration
    // This is used to register PayTR as a custom provider in HighLevel marketplace
    'provider' => [
        'title' => env('HIGHLEVEL_PROVIDER_TITLE', 'PayTR'),
        'description' => env('HIGHLEVEL_PROVIDER_DESCRIPTION', 'PayTR Payment Gateway for Turkey'),
        'image_url' => env('HIGHLEVEL_PROVIDER_IMAGE_URL', null),
        'query_url' => env('APP_URL') . '/api/payments/query',
        'payments_url' => env('APP_URL') . '/payments/page',
    ],
],
```

---

## Correct API Endpoint Found

After researching the HighLevel documentation, the correct endpoint for creating a third-party payment provider integration is:

**Endpoint:** `POST /payments/custom-provider/provider`
**Full URL:** `https://services.leadconnectorhq.com/payments/custom-provider/provider`
**Purpose:** "API to create a new association for an app and location"

This is the "Create New Integration" API that associates your marketplace app with a location and registers it as a payment provider.

**Expected Response Codes:**
- `200`: Successful integration created
- `400`: Bad Request (invalid parameters)
- `401`: Unauthorized (invalid or missing token)
- `422`: Unprocessable Entity (validation errors)

**Additional Endpoint (for credentials):**
- `POST /payments/custom-provider/connect` - For configuring test/live mode credentials (apiKey, publishableKey)

---

## Summary of Changes

### What Was Wrong:
1. Using white-label provider endpoint (for NMI/Authorize.net only)
2. Attempting to use Company-level OAuth token for marketplace-app-only endpoint
3. Wrong marketplace app category configuration
4. Incorrect understanding of HighLevel's payment provider integration types

### What Needs to Change:
1. Change app category from "White Label Payment Provider" to "Third Party Provider"
2. Change API endpoint from `/payments/integrations/provider/whitelabel` to `/payments/integrations` (or similar)
3. Update payload structure to match Third Party Provider requirements
4. Remove white-label specific fields (`uniqueName`, `provider`)
5. Add required Third Party Provider fields (`queryUrl`, `paymentsUrl`)
6. Keep using standard OAuth tokens (Company or Location level)

### Authentication Flow (Correct):
1. User installs marketplace app
2. OAuth redirect with authorization code
3. Exchange code for access token (Company or Location level)
4. Use access token to call "Create Public Provider Config API"
5. Provider appears in user's Payments > Integrations page
6. User configures credentials via your custom page
7. You call Connect Config API to submit apiKey and publishableKey
8. Payment processing becomes available

---

## Next Steps

1. **Verify Marketplace App Category:** Change to "Third Party Provider" in marketplace dashboard
2. **Find Correct API Endpoint:** Research HighLevel documentation for exact endpoint
3. **Update Code:** Implement changes outlined above
4. **Test OAuth Flow:** Ensure token exchange still works
5. **Test Provider Registration:** Verify provider appears in user's integration list
6. **Implement Query URL:** Build backend endpoint to handle verification requests
7. **Implement Payments URL:** Build iframe page for payment processing
8. **Test End-to-End:** Complete payment flow from installation to transaction

---

## References

- HighLevel Documentation: "How to build a custom payments integration on the platform"
- Article URL: https://help.gohighlevel.com/support/solutions/articles/155000002620
- Technical Workflow: `/Users/volkanoluc/Projects/highlevel-paytr-integration/docs/highlevel-api-documentation/technical_workflow.md`
- White-Label Documentation: https://help.gohighlevel.com/support/solutions/articles/155000002747
- Marketplace Payments Module: https://marketplace.gohighlevel.com/docs/marketplace-modules/Payments/

---

## Key Insight

**The white-label provider endpoint is NOT for custom payment gateways. It's only for reselling/rebranding HighLevel's existing NMI and Authorize.net integrations. PayTR needs to use the Third Party Provider approach with standard OAuth tokens.**
