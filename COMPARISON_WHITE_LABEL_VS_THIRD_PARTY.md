# White-Label Provider vs Third Party Provider

## Visual Comparison

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    TWO DIFFERENT INTEGRATION APPROACHES                     │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────┬──────────────────────────────────────┐
│     WHITE-LABEL PAYMENT PROVIDER     │     THIRD PARTY PROVIDER (PayTR)     │
│          (NOT FOR PAYTR)             │         (CORRECT APPROACH)           │
├──────────────────────────────────────┼──────────────────────────────────────┤
│                                      │                                      │
│  Purpose:                            │  Purpose:                            │
│  • Resell existing HighLevel         │  • Integrate custom payment gateway  │
│    payment processors                │  • Full control over payment flow    │
│  • White-label NMI or Authorize.net  │  • Independent payment processor     │
│                                      │                                      │
│  Base Provider Required:             │  Base Provider Required:             │
│  ✓ NMI                               │  ✗ None - standalone gateway         │
│  ✓ Authorize.net                     │                                      │
│                                      │                                      │
│  API Endpoint:                       │  API Endpoint:                       │
│  /payments/integrations/provider/    │  /payments/custom-provider/provider  │
│  whitelabel                          │                                      │
│                                      │                                      │
│  Authentication Required:            │  Authentication Required:            │
│  • Marketplace-app token             │  • Standard OAuth token              │
│    (special token type)              │    (Company or Location level)       │
│                                      │                                      │
│  Marketplace Category:               │  Marketplace Category:               │
│  • "White Label Payment Provider"    │  • "Third Party Provider"            │
│                                      │                                      │
│  Payload Fields:                     │  Payload Fields:                     │
│  • uniqueName                        │  • name                              │
│  • provider ("nmi"/"authorize-net")  │  • description                       │
│  • description                       │  • imageUrl                          │
│  • imageUrl                          │  • locationId                        │
│  • locationId                        │  • queryUrl (your backend)           │
│                                      │  • paymentsUrl (your iframe)         │
│                                      │                                      │
│  Examples:                           │  Examples:                           │
│  • Payment processor reselling NMI   │  • PayTR (Turkish gateway)           │
│  • Agency white-labeling Auth.net    │  • Stripe custom integration         │
│  • Branded NMI for clients           │  • Adyen custom integration          │
│                                      │  • Any independent gateway           │
│                                      │                                      │
│  Payment Flow:                       │  Payment Flow:                       │
│  • Uses HighLevel's native NMI/      │  • Custom iframe page YOU build      │
│    Auth.net infrastructure           │  • PostMessage communication         │
│  • Branded with your logo/name       │  • Direct integration with gateway   │
│                                      │  • Complete control over UX          │
│                                      │                                      │
└──────────────────────────────────────┴──────────────────────────────────────┘
```

---

## Why PayTR Must Use Third Party Provider

### PayTR Characteristics

- **Independent gateway** - PayTR has its own payment processing infrastructure
- **Turkish market** - Specialized for Turkey (TRY currency, local banks)
- **Custom API** - PayTR's own API endpoints and authentication
- **Own iframe system** - PayTR provides its own payment forms
- **Not built on NMI/Auth.net** - Completely separate payment processor

### Decision Matrix

| Question | Answer | Conclusion |
|----------|--------|------------|
| Is PayTR built on top of NMI? | NO | ✗ White-Label |
| Is PayTR built on top of Authorize.net? | NO | ✗ White-Label |
| Does PayTR have its own API? | YES | ✓ Third Party |
| Do you control the payment flow? | YES | ✓ Third Party |
| Do you provide custom iframe pages? | YES | ✓ Third Party |

**Result: PayTR = Third Party Provider**

---

## The Error Explained

```
Error: "This authClass type is not allowed to access this scope"

Why?
┌─────────────────────────────────────────────────────────────────┐
│  You tried to call:                                             │
│  POST /payments/integrations/provider/whitelabel                │
│                                                                  │
│  With:                                                           │
│  Authorization: Bearer <Company-level OAuth token>              │
│  authClass: "Company"                                            │
│                                                                  │
│  But this endpoint requires:                                    │
│  Authorization: Bearer <Marketplace-app token>                  │
│  authClass: "Marketplace" or "App" (not Company/Location)       │
│                                                                  │
│  HighLevel's IAM system blocked the request because:            │
│  ✗ Company-level token cannot access white-label endpoints      │
│  ✗ White-label endpoints are restricted to marketplace-app      │
│    tokens only                                                   │
└─────────────────────────────────────────────────────────────────┘

Solution:
┌─────────────────────────────────────────────────────────────────┐
│  Call the correct endpoint:                                     │
│  POST /payments/custom-provider/provider                        │
│                                                                  │
│  With:                                                           │
│  Authorization: Bearer <Company-level OAuth token>              │
│  authClass: "Company"                                            │
│                                                                  │
│  This endpoint accepts:                                          │
│  ✓ Company-level OAuth tokens                                   │
│  ✓ Location-level OAuth tokens                                  │
│  ✓ Standard OAuth flow (no special marketplace-app token)       │
└─────────────────────────────────────────────────────────────────┘
```

---

## Code Comparison

### Wrong Approach (White-Label)

```php
// ❌ WRONG - This is for NMI/Authorize.net resellers only
$payload = [
    'uniqueName' => 'paytr-direct',
    'provider' => 'nmi', // PayTR is NOT built on NMI!
    'description' => 'PayTR Payment Gateway',
    'imageUrl' => 'https://...',
    'locationId' => $account->location_id,
];

$response = Http::withToken($account->access_token)
    ->post('https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel', $payload);

// Result: ❌ 401 Unauthorized
// Error: "This authClass type is not allowed to access this scope"
```

### Correct Approach (Third Party)

```php
// ✅ CORRECT - For custom gateways like PayTR
$payload = [
    'name' => 'PayTR',
    'description' => 'PayTR Payment Gateway for Turkey',
    'imageUrl' => 'https://...',
    'locationId' => $account->location_id,
    'queryUrl' => 'https://your-app.com/api/payments/query',
    'paymentsUrl' => 'https://your-app.com/payments/page',
];

$response = Http::withToken($account->access_token)
    ->post('https://services.leadconnectorhq.com/payments/custom-provider/provider', $payload);

// Result: ✅ 200 OK
// Returns: { "id": "...", "name": "PayTR", "status": "active" }
```

---

## Token Type Explanation

### Company-Level OAuth Token (What You Have)

```
┌─────────────────────────────────────────────────────────────────┐
│  Token Type: OAuth 2.0 Bearer Token                            │
│  User Type: Company                                             │
│  Auth Class: "Company"                                          │
│                                                                  │
│  Obtained via:                                                   │
│  1. User installs marketplace app                               │
│  2. OAuth redirect with authorization code                      │
│  3. Exchange code for token with user_type: "Company"           │
│                                                                  │
│  Can access:                                                     │
│  ✓ Agency-level APIs                                            │
│  ✓ Sub-account creation                                         │
│  ✓ Company settings                                             │
│  ✓ Third Party Provider APIs ← YOU NEED THIS                    │
│  ✗ White-Label Provider APIs (blocked by IAM)                   │
│                                                                  │
│  Format:                                                         │
│  Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...  │
└─────────────────────────────────────────────────────────────────┘
```

### Marketplace-App Token (What White-Label Needs)

```
┌─────────────────────────────────────────────────────────────────┐
│  Token Type: Special Marketplace Token                         │
│  User Type: N/A (app-level, not user-level)                    │
│  Auth Class: "Marketplace" or "App"                             │
│                                                                  │
│  Obtained via:                                                   │
│  Unknown - HighLevel documentation doesn't clearly explain      │
│  Likely: Special credentials for white-label partners only      │
│                                                                  │
│  Can access:                                                     │
│  ✓ White-Label Provider APIs (NMI/Auth.net registration)        │
│  ✗ Regular payment APIs                                         │
│  ✗ Location-specific operations                                 │
│                                                                  │
│  Use case:                                                       │
│  Only for payment processors reselling HighLevel's native       │
│  NMI or Authorize.net infrastructure                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Configuration Comparison

### White-Label Config (Wrong)

```php
// config/services.php
'highlevel' => [
    'whitelabel' => [
        'unique_name' => 'paytr-direct',
        'provider' => 'nmi', // ❌ PayTR is not NMI
        'title' => 'PayTR',
        'description' => 'PayTR Payment Gateway',
        'image_url' => 'https://...',
    ],
],
```

### Third Party Config (Correct)

```php
// config/services.php
'highlevel' => [
    'provider' => [
        'title' => 'PayTR',
        'description' => 'PayTR Payment Gateway for Turkey',
        'image_url' => 'https://...',
        'query_url' => env('APP_URL') . '/api/payments/query',
        'payments_url' => env('APP_URL') . '/payments/page',
    ],
],
```

---

## When To Use Each Approach

### Use White-Label Provider When:

- ✓ You ARE a payment processor reselling NMI
- ✓ You ARE a payment processor reselling Authorize.net
- ✓ You want to brand HighLevel's existing payment infrastructure
- ✓ You have a partnership with HighLevel for white-label access
- ✓ You DON'T need custom payment logic

### Use Third Party Provider When:

- ✓ You have a custom/independent payment gateway (like PayTR)
- ✓ You need full control over payment flow
- ✓ Your gateway is NOT NMI or Authorize.net
- ✓ You build custom iframe payment pages
- ✓ You want to integrate ANY payment processor
- ✓ You implement your own payment verification logic

---

## Common Misconceptions

### ❌ Myth 1: "White-Label means custom branding"
**Reality:** "White-Label" in HighLevel specifically means reselling their NMI/Auth.net infrastructure, not general custom branding.

### ❌ Myth 2: "I need special marketplace-app token"
**Reality:** Only for white-label. Third party providers use standard OAuth tokens.

### ❌ Myth 3: "My auth was wrong"
**Reality:** Your OAuth flow was correct! You just called the wrong API endpoint.

### ❌ Myth 4: "authClass error means IAM misconfiguration"
**Reality:** The IAM is working correctly - it's blocking you from the wrong endpoint and directing you to use the right one.

---

## Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│  PayTR is a THIRD PARTY PROVIDER, not a WHITE-LABEL PROVIDER    │
│                                                                  │
│  Use endpoint: /payments/custom-provider/provider               │
│  Not endpoint: /payments/integrations/provider/whitelabel       │
│                                                                  │
│  Your OAuth token is correct - just call the right API!         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## References

- **Official HighLevel Third Party Guide:** https://help.gohighlevel.com/support/solutions/articles/155000002620
- **Official HighLevel White-Label Guide:** https://help.gohighlevel.com/support/solutions/articles/155000002747
- **Custom Provider API Docs:** https://marketplace.gohighlevel.com/docs/ghl/payments/custom-provider/
- **White-Label API Docs:** https://marketplace.gohighlevel.com/docs/ghl/payments/create-integration-provider/
