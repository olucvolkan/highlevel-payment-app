# Authentication Issue Solution Summary

## Problem Identified

You received this error:
```json
{
  "message": "This authClass type is not allowed to access this scope. Please verify your IAM configuration if this is not the case."
}
```

## Root Cause

**You are using the wrong integration approach for PayTR.**

Your application is attempting to use the **White-Label Payment Provider** API endpoint (`/payments/integrations/provider/whitelabel`), which is:

1. Only for payment solutions built **on top of NMI or Authorize.net**
2. Requires a special "marketplace-app token" (not standard OAuth tokens)
3. Designed for reselling/rebranding HighLevel's existing payment infrastructure

**PayTR is a completely independent Turkish payment gateway** and should use the **Third Party Provider** approach instead.

---

## Solution: Use Third Party Provider Integration

### What Changes Are Needed

| Current (Wrong) | Correct |
|-----------------|---------|
| White-Label Payment Provider API | Third Party Provider API |
| `/payments/integrations/provider/whitelabel` | `/payments/custom-provider/provider` |
| Marketplace-app token required | Standard OAuth token works |
| Built on NMI/Authorize.net | Custom independent gateway |

---

## Implementation Steps

### 1. Update Marketplace App Configuration

In your HighLevel marketplace app dashboard:
- **Change category from:** "White Label Payment Provider"
- **To:** "Third Party Provider"

This ensures your app appears correctly in the marketplace and on Payments > Integrations page.

### 2. Update Code - Change API Endpoint

**File:** `/Users/volkanoluc/Projects/highlevel-paytr-integration/app/Services/HighLevelService.php`
**Line:** 370

**Change from:**
```php
->post('https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel', $payload);
```

**To:**
```php
->post('https://services.leadconnectorhq.com/payments/custom-provider/provider', $payload);
```

### 3. Update Method Name and Payload

**Change method name from:**
```php
public function createThirdPartyProvider(HLAccount $account, array $config): array
```

**To:**
```php
public function createPaymentProviderIntegration(HLAccount $account, array $config): array
```

**Update payload structure to:**
```php
$payload = [
    'name' => $config['title'], // e.g., "PayTR"
    'description' => $config['description'], // e.g., "PayTR Payment Gateway for Turkey"
    'imageUrl' => $config['imageUrl'], // Your provider logo URL
    'locationId' => $account->location_id,
    'queryUrl' => config('app.url') . '/api/payments/query',
    'paymentsUrl' => config('app.url') . '/payments/page',
];
```

**Remove these fields (white-label specific):**
- `uniqueName`
- `provider` (the "nmi" or "authorize-net" field)

### 4. Update OAuthController

**File:** `/Users/volkanoluc/Projects/highlevel-paytr-integration/app/Http/Controllers/OAuthController.php`
**Line:** 71

**Change from:**
```php
$whitelabelResult = $this->highLevelService->createThirdPartyProvider($account, [
    'uniqueName' => config('services.highlevel.whitelabel.unique_name'),
    'title' => config('services.highlevel.whitelabel.title'),
    'provider' => config('services.highlevel.whitelabel.provider'),
    'description' => config('services.highlevel.whitelabel.description'),
    'imageUrl' => config('services.highlevel.whitelabel.image_url'),
]);
```

**To:**
```php
$providerResult = $this->highLevelService->createPaymentProviderIntegration($account, [
    'title' => config('services.highlevel.provider.title'),
    'description' => config('services.highlevel.provider.description'),
    'imageUrl' => config('services.highlevel.provider.image_url'),
]);
```

### 5. Update Configuration File

**File:** `/Users/volkanoluc/Projects/highlevel-paytr-integration/config/services.php`

**Change from:**
```php
'highlevel' => [
    // ... other config ...
    'whitelabel' => [
        'unique_name' => env('HIGHLEVEL_WHITELABEL_UNIQUE_NAME', 'paytr-direct'),
        'title' => env('HIGHLEVEL_WHITELABEL_TITLE', 'PayTR'),
        'provider' => env('HIGHLEVEL_WHITELABEL_PROVIDER', 'nmi'),
        'description' => env('HIGHLEVEL_WHITELABEL_DESCRIPTION', 'PayTR Payment Gateway for Turkey'),
        'image_url' => env('HIGHLEVEL_WHITELABEL_IMAGE_URL', null),
    ],
],
```

**To:**
```php
'highlevel' => [
    // ... other config ...
    'provider' => [
        'title' => env('HIGHLEVEL_PROVIDER_TITLE', 'PayTR'),
        'description' => env('HIGHLEVEL_PROVIDER_DESCRIPTION', 'PayTR Payment Gateway for Turkey'),
        'image_url' => env('HIGHLEVEL_PROVIDER_IMAGE_URL', null),
        'query_url' => env('APP_URL') . '/api/payments/query',
        'payments_url' => env('APP_URL') . '/payments/page',
    ],
],
```

### 6. Update Environment Variables

**File:** `.env`

**Remove these (white-label specific):**
```bash
HIGHLEVEL_WHITELABEL_UNIQUE_NAME=paytr-direct
HIGHLEVEL_WHITELABEL_PROVIDER=nmi
```

**Add/Update these:**
```bash
HIGHLEVEL_PROVIDER_TITLE="PayTR"
HIGHLEVEL_PROVIDER_DESCRIPTION="PayTR Payment Gateway for Turkey"
HIGHLEVEL_PROVIDER_IMAGE_URL="https://your-domain.com/images/paytr-logo.png"
```

---

## Authentication Stays The Same

**Good news:** Your current OAuth authentication is correct!

- Continue using `user_type: "Company"` in token exchange
- Continue using `Http::withToken($account->access_token)`
- The Company-level OAuth token works for the Third Party Provider API

The error was NOT about the token type - it was about using the wrong API endpoint entirely.

---

## Expected Response After Fix

Once you switch to the correct endpoint, you should receive a `200 OK` response like:

```json
{
  "id": "provider_12345",
  "name": "PayTR",
  "description": "PayTR Payment Gateway for Turkey",
  "locationId": "location_abc123",
  "status": "active",
  "createdAt": "2025-12-02T10:30:00Z"
}
```

---

## Next Steps After This Fix

Once the provider integration is successfully created:

1. **Implement Query URL endpoint** (`/api/payments/query`):
   - Handle `verify`, `list_payment_methods`, `charge_payment`, `refund` actions
   - Verify requests using `apiKey` parameter from HighLevel

2. **Implement Payments URL** (`/payments/page`):
   - Create iframe-compatible payment page
   - Implement postMessage events (`custom_provider_ready`, `custom_element_success_response`, etc.)
   - Integrate PayTR iframe payment flow

3. **Implement Connect Config API calls**:
   - Create configuration page for users to input PayTR credentials
   - Call `/payments/custom-provider/connect` to submit test/live credentials
   - Store `apiKey` and `publishableKey` in HighLevel

4. **Test complete payment flow**:
   - Install app in test location
   - Configure PayTR credentials
   - Make test payment
   - Verify payment status
   - Test refunds

---

## Quick Reference: API Endpoints

| Purpose | Endpoint | Method | When to Call |
|---------|----------|--------|--------------|
| Create Integration | `/payments/custom-provider/provider` | POST | During OAuth callback (after token exchange) |
| Connect Credentials | `/payments/custom-provider/connect` | POST | When user configures PayTR credentials |
| Verify Payment | `/api/payments/query` | POST | Your endpoint - HighLevel calls this |
| Payment Page | `/payments/page` | GET | Your page - loaded in iframe by HighLevel |

---

## Files to Modify

1. `/Users/volkanoluc/Projects/highlevel-paytr-integration/app/Services/HighLevelService.php` (line 366-372)
2. `/Users/volkanoluc/Projects/highlevel-paytr-integration/app/Http/Controllers/OAuthController.php` (line 70-77)
3. `/Users/volkanoluc/Projects/highlevel-paytr-integration/config/services.php` (line 58-68)
4. `/Users/volkanoluc/Projects/highlevel-paytr-integration/.env`

---

## Why This Happened

The confusion arose because HighLevel has two very similar-sounding approaches:

1. **White-Label Payment Provider** - for reselling NMI/Authorize.net
2. **Third Party Provider** - for custom gateways like PayTR

Both involve "custom payment integration" but use completely different APIs and authentication methods.

Your PayTR integration is a true custom gateway, so it must use approach #2.

---

## Documentation References

- **Third Party Provider Guide:** https://help.gohighlevel.com/support/solutions/articles/155000002620
- **Custom Provider API:** https://marketplace.gohighlevel.com/docs/ghl/payments/custom-provider/
- **Technical Workflow:** `/Users/volkanoluc/Projects/highlevel-paytr-integration/docs/highlevel-api-documentation/technical_workflow.md`
- **White-Label (Wrong Approach):** https://help.gohighlevel.com/support/solutions/articles/155000002747

---

## Key Takeaway

**The white-label endpoint is ONLY for NMI and Authorize.net resellers. PayTR needs the third-party provider endpoint, which works with standard OAuth tokens.**

Your authentication was correct all along - you just needed to call a different API endpoint!
