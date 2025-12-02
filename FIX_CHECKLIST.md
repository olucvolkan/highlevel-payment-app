# Authentication Issue Fix Checklist

## The Problem
Error: "This authClass type is not allowed to access this scope"

**Root Cause:** Using White-Label Provider API (for NMI/Authorize.net only) instead of Third Party Provider API (for custom gateways like PayTR)

---

## Quick Fix - 5 Steps

### [ ] Step 1: Change Marketplace App Category
- Go to HighLevel Marketplace Dashboard
- Open your PayTR integration app settings
- Change category from "White Label Payment Provider" to "Third Party Provider"
- Save changes

### [ ] Step 2: Update API Endpoint in HighLevelService.php

**File:** `app/Services/HighLevelService.php`
**Line:** 370

```diff
- ->post('https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel', $payload);
+ ->post('https://services.leadconnectorhq.com/payments/custom-provider/provider', $payload);
```

### [ ] Step 3: Update Method Name and Payload

**Same file, around line 350-390:**

```diff
- public function createThirdPartyProvider(HLAccount $account, array $config): array
+ public function createPaymentProviderIntegration(HLAccount $account, array $config): array

  $payload = [
-     'uniqueName' => $config['uniqueName'],
+     'name' => $config['title'],
      'description' => $config['description'],
      'imageUrl' => $config['imageUrl'],
-     'provider' => $config['provider'], // Remove this
      'locationId' => $account->location_id,
+     'queryUrl' => config('app.url') . '/api/payments/query',
+     'paymentsUrl' => config('app.url') . '/payments/page',
  ];
```

### [ ] Step 4: Update OAuthController.php

**File:** `app/Http/Controllers/OAuthController.php`
**Line:** 71

```diff
- $whitelabelResult = $this->highLevelService->createThirdPartyProvider($account, [
-     'uniqueName' => config('services.highlevel.whitelabel.unique_name'),
+ $providerResult = $this->highLevelService->createPaymentProviderIntegration($account, [
      'title' => config('services.highlevel.provider.title'),
-     'provider' => config('services.highlevel.whitelabel.provider'),
      'description' => config('services.highlevel.provider.description'),
      'imageUrl' => config('services.highlevel.provider.image_url'),
  ]);
```

### [ ] Step 5: Update config/services.php

**File:** `config/services.php`
**Line:** 58-68

```diff
  'highlevel' => [
      // ... other config ...
-     'whitelabel' => [
-         'unique_name' => env('HIGHLEVEL_WHITELABEL_UNIQUE_NAME', 'paytr-direct'),
+     'provider' => [
          'title' => env('HIGHLEVEL_PROVIDER_TITLE', 'PayTR'),
-         'provider' => env('HIGHLEVEL_WHITELABEL_PROVIDER', 'nmi'),
          'description' => env('HIGHLEVEL_PROVIDER_DESCRIPTION', 'PayTR Payment Gateway for Turkey'),
          'image_url' => env('HIGHLEVEL_PROVIDER_IMAGE_URL', null),
+         'query_url' => env('APP_URL') . '/api/payments/query',
+         'payments_url' => env('APP_URL') . '/payments/page',
      ],
  ],
```

---

## Testing

### [ ] Test 1: OAuth Flow
```bash
# Start the app
php artisan serve

# Navigate to OAuth URL in browser
# https://marketplace.gohighlevel.com/oauth/chooselocation?...

# Check logs for successful provider creation
tail -f storage/logs/laravel.log
```

### [ ] Test 2: Verify API Call Success
Look for log entry:
```
HighLevel payment provider integration created successfully
```

Instead of:
```
Failed to register white-label provider
```

### [ ] Test 3: Check Response
Response should be `200 OK` with provider ID, not `401 Unauthorized`

---

## Rollback Plan (If Needed)

If something goes wrong, revert these commits:
```bash
git log --oneline -5  # Find commit hash before changes
git revert <commit-hash>
```

---

## What Stays The Same

✅ OAuth authentication flow (no changes needed)
✅ Token exchange with `user_type: "Company"` (correct)
✅ Using `Http::withToken($account->access_token)` (correct)
✅ Database schema and migrations (no changes needed)

**Only the API endpoint and payload structure change.**

---

## Expected Outcome

After these changes:
- ✅ OAuth callback completes successfully
- ✅ Provider appears in location's Payments > Integrations
- ✅ No more "authClass type is not allowed" error
- ✅ Standard OAuth token works (Company-level token is correct for this API)

---

## Time Estimate

- **Code changes:** 10-15 minutes
- **Marketplace app update:** 2-3 minutes
- **Testing:** 5-10 minutes
- **Total:** ~20-30 minutes

---

## Support Resources

- **Detailed Analysis:** `/Users/volkanoluc/Projects/highlevel-paytr-integration/AUTHENTICATION_ANALYSIS.md`
- **Solution Summary:** `/Users/volkanoluc/Projects/highlevel-paytr-integration/SOLUTION_SUMMARY.md`
- **Technical Workflow:** `/Users/volkanoluc/Projects/highlevel-paytr-integration/docs/highlevel-api-documentation/technical_workflow.md`
- **HighLevel Docs:** https://marketplace.gohighlevel.com/docs/ghl/payments/custom-provider/
