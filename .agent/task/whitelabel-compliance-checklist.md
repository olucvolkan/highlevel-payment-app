# Whitelabel Compliance Checklist

## Overview

This document provides a comprehensive checklist for ensuring the HighTr PayTR payment integration app is **fully whitelabel-compliant** and contains no references to "HighLevel" or related branding that could violate marketplace policies.

**Why This Matters:**
- HighLevel marketplace apps must be whitelabel to allow white-label agencies to use them
- Any HighLevel branding visible to end users violates marketplace terms
- Violations block marketplace approval and can result in app removal

**Rejection Feedback:**
> "Since your APP is a whitelabel APP mention of any HighLevel references across the APP is a breach of whitelabel. Please make changes."

---

## Whitelabel Compliance Principles

### What Must Be Removed
1. **Brand Names:**
   - "HighLevel"
   - "GHL"
   - "Go High Level"
   - "Lead Connector"
   - Any HighLevel product names

2. **Domain Names:**
   - `app.gohighlevel.com`
   - `services.leadconnectorhq.com`
   - `backend.leadconnectorhq.com`
   - Any HighLevel-owned domains in user-facing content

3. **Platform-Specific Terminology:**
   - "Location token" (use "access token" or "authorization token")
   - "Company token" (use "account token")
   - "HL account" (use "account" or "integration")
   - Other HighLevel-specific jargon

4. **Visual Branding:**
   - HighLevel logos
   - HighLevel color schemes (when obviously branded)
   - HighLevel UI elements that identify the platform
   - Screenshots showing HighLevel branding

### What Is Allowed
1. **Technical References (Backend Only):**
   - HighLevel API endpoints in configuration files
   - HighLevel in server-side logs
   - HighLevel in code comments (not user-facing)
   - HighLevel in developer documentation (private)

2. **Generic Platform References:**
   - "Your CRM"
   - "The platform"
   - "Your system"
   - "The application"
   - "Integration"

---

## Comprehensive Audit Checklist

### 1. User Interface (Views)

#### Error Pages
- [ ] `resources/views/oauth/error.blade.php`
  - Line 145: "PayTR integration with HighLevel" → "PayTR integration"
  - Line 158: References to HighLevel in troubleshooting
  - Line 168: Support email mentions
  - Line 201: Redirect to gohighlevel.com

- [ ] `resources/views/oauth/success.blade.php`
  - Check success message for HighLevel references
  - Verify redirect URLs don't expose HighLevel

- [ ] `resources/views/payments/error.blade.php`
  - Check for platform-specific error messages

- [ ] `resources/views/payments/success.blade.php`
  - Verify success messages are generic

#### Setup/Configuration Pages
- [ ] `resources/views/paytr/setup.blade.php`
  - Check page title and headers
  - Verify form labels and help text
  - Check any instructional content

- [ ] `resources/views/paytr/setup-highlevel.blade.php`
  - **FILENAME VIOLATION**: Contains "highlevel"
  - Review content for HighLevel references
  - Consider renaming file

- [ ] `resources/views/paytr/connect.blade.php`
  - Check connection flow messaging

- [ ] `resources/views/paytr/connect-success.blade.php`
  - Verify success messages

#### Landing/Marketing Pages
- [ ] `resources/views/landing.blade.php`
  - Check marketing copy
  - Verify feature descriptions
  - Check call-to-action text

- [ ] `resources/views/welcome.blade.php`
  - Review welcome messaging

#### Payment Pages
- [ ] `resources/views/payments/iframe.blade.php`
  - Check iframe messaging
  - Verify loading states
  - Check postMessage calls (must not expose HighLevel to user)

- [ ] `resources/views/payments/setup-required.blade.php`
  - Verify setup instructions

#### Layouts
- [ ] `resources/views/layouts/landing.blade.php`
  - Check header/footer text
  - Verify navigation labels
  - Check meta tags and page titles

### 2. Controllers (User-Facing Messages)

#### OAuthController
- [ ] `app/Http/Controllers/OAuthController.php`
  - Line 32: Error message "Authorization code missing"
  - Line 43: "Token exchange failed: ..." - May expose HighLevel error
  - Line 56: "Location ID not found in OAuth response" - OK
  - Line 67: "Failed to create account" - OK
  - Line 90: "Failed to obtain Location token" - Change to "Integration setup failed"
  - Line 148: "Integration successfully installed!" - OK
  - Line 154: "HighLevel integration completed!" - Change to "Integration completed!"

#### PaymentController
- [ ] `app/Http/Controllers/PaymentController.php`
  - Review all error responses
  - Check payment failure messages
  - Verify transaction error messages

#### WebhookController
- [ ] `app/Http/Controllers/WebhookController.php`
  - Check webhook error responses (usually not user-facing)
  - Verify any user notifications from webhooks

#### PayTRSetupController
- [ ] `app/Http/Controllers/PayTRSetupController.php`
  - Check setup validation messages
  - Verify configuration error messages
  - Check success confirmations

#### HighLevelProviderController
- [ ] `app/Http/Controllers/HighLevelProviderController.php`
  - **FILENAME CONCERN**: Contains "HighLevel" but OK for backend
  - Review any user-facing responses

### 3. JavaScript/Frontend

#### Public Assets
- [ ] `public/test-payment.html`
  - Check for HighLevel references in test page
  - Verify this is not publicly accessible in production

#### JavaScript Files
- [ ] Search `resources/js/` for "HighLevel"
- [ ] Search `public/js/` for "HighLevel"
- [ ] Check console.log statements that might expose HighLevel
- [ ] Verify postMessage calls don't expose platform details

#### CSS/Styling
- [ ] Check for HighLevel-branded color schemes
- [ ] Verify no HighLevel logos in CSS backgrounds

### 4. Configuration Files (Public)

#### Marketplace Configuration
- [ ] `marketplace.json`
  - Line 2: App name - No "HighLevel" reference ✓
  - Line 8: Description - Contains "HighLevel" - **REMOVE**
  - Line 9: Long description - Contains "HighLevel" - **REMOVE**
  - All URLs - OK (configuration, not user-facing)

- [ ] `public/marketplace.json`
  - Same checks as above

#### Documentation (Public-Facing)
- [ ] `MARKETPLACE.md` (if exists and public)
  - Check for HighLevel references in user documentation

### 5. API Responses

#### Payment Query Endpoint
- [ ] `/api/payments/query`
  - Check all JSON error responses
  - Verify success messages
  - Check validation error messages

#### Webhook Endpoints
- [ ] `/api/webhooks/marketplace`
  - Verify responses don't expose HighLevel (usually OK, system-to-system)

- [ ] `/api/callbacks/paytr`
  - Check any user-facing messages in callback flow

### 6. Screenshots & Images

#### Marketplace Screenshots
- [ ] `public/images/screenshot-1.png` (currently missing)
  - Must not show "HighLevel" branding
  - Must not show HighLevel UI elements
  - Must not show HighLevel URLs

- [ ] `public/images/screenshot-2.png` (currently missing)
  - Same requirements as above

#### App Assets
- [ ] `public/images/paytr-logo.png`
  - Verify no HighLevel branding

- [ ] Check for any other images in `public/images/`

### 7. Email Templates (If Any)

- [ ] Search `resources/views/emails/` for HighLevel references
- [ ] Check email subjects
- [ ] Check email body content
- [ ] Verify sender name doesn't reference HighLevel

### 8. Database/Models (User-Visible Data)

#### Model Attributes
- [ ] `app/Models/HLAccount.php`
  - **FILENAME**: "HL" abbreviation - OK for backend
  - Check any user-visible attributes or accessors
  - Verify toString/toArray methods for user data

- [ ] `app/Models/Payment.php`
  - Check status messages
  - Verify error messages stored in database

- [ ] `app/Models/UserActivityLog.php`
  - Check action names shown to users

### 9. Logging (User-Visible)

#### UserActionLogger
- [ ] `app/Logging/UserActionLogger.php`
  - Check any messages shown to users
  - Verify activity descriptions

### 10. Routes (URL Patterns)

- [ ] `routes/web.php`
  - Check route names exposed in URLs
  - Verify no "highlevel" in public-facing URLs

- [ ] `routes/api.php`
  - Check API endpoint paths
  - Verify no platform-specific terminology in public APIs

---

## Automated Search Commands

Run these commands to find potential violations:

### Search for "HighLevel" References
```bash
# User-facing files only
grep -r "HighLevel" resources/views/
grep -r "HighLevel" app/Http/Controllers/
grep -r "HighLevel" public/
grep -r "HighLevel" resources/js/

# Case-insensitive
grep -ri "highlevel" resources/views/
grep -ri "highlevel" app/Http/Controllers/

# Find in JavaScript
grep -r "HighLevel" resources/js/ public/js/
```

### Search for Domain References
```bash
# Find HighLevel domains
grep -r "leadconnectorhq.com" resources/
grep -r "gohighlevel.com" resources/
grep -r "gohighlevel.com" public/

# In templates
grep -r "app.gohighlevel.com" resources/views/
```

### Search for Abbreviations
```bash
# Find "GHL" references
grep -r "GHL" resources/views/
grep -r "GHL" app/Http/Controllers/

# Find "HL" in user-facing contexts
grep -r "HL " resources/views/
```

### Search in Specific File Types
```bash
# All Blade templates
find resources/views -name "*.blade.php" -exec grep -l "HighLevel" {} \;

# All JavaScript
find resources/js public/js -name "*.js" -exec grep -l "HighLevel" {} \;

# All controllers
find app/Http/Controllers -name "*.php" -exec grep -l "HighLevel" {} \;
```

---

## Whitelabel Terminology Guide

Use this guide to replace HighLevel-specific terms with whitelabel alternatives:

| Instead of... | Use... |
|---------------|--------|
| HighLevel | Your CRM / The platform |
| HighLevel account | Your account / Integration |
| HighLevel location | Your location / Workspace |
| Location token | Access token / Authorization token |
| Company token | Account token |
| HL account | Account / Integration |
| GHL | The platform |
| Go High Level | Your CRM system |
| leadconnectorhq.com | [API endpoint] / [service] |
| gohighlevel.com | Your dashboard / Platform |
| HighLevel OAuth | OAuth authentication |
| HighLevel API | Platform API |

### Example Replacements

**Before (Non-Compliant):**
```
"We encountered an issue while setting up your PayTR integration with HighLevel."
"Failed to obtain Location token from HighLevel API"
"Your HighLevel account has been successfully connected"
"Visit app.gohighlevel.com to manage your settings"
```

**After (Whitelabel-Compliant):**
```
"We encountered an issue while setting up your PayTR integration."
"Integration setup encountered an error. Please contact support."
"Your account has been successfully connected"
"Visit your platform dashboard to manage your settings"
```

---

## Manual Visual Inspection

Beyond automated searches, manually inspect:

### Browser Testing
1. **Install app from marketplace:**
   - Check OAuth flow UI
   - Inspect all error messages
   - Verify success messages

2. **Browser console:**
   - Check for HighLevel in console.log
   - Verify no API endpoints exposed in console

3. **Network tab:**
   - Check XHR request URLs (OK if not visible to user)
   - Verify response messages don't expose HighLevel

4. **Screenshots:**
   - View actual marketplace listing
   - Verify screenshots are whitelabel

### User Journey Testing
Test as if you're a non-technical user:
1. Install app from marketplace
2. Complete OAuth flow
3. Configure PayTR credentials
4. Try to make payment
5. Encounter an error intentionally
6. Check all messages you see

**Question:** Did you see "HighLevel" anywhere?
- If YES: Document location and fix
- If NO: Whitelabel compliance likely achieved

---

## Final Whitelabel Approval Checklist

Before resubmission, verify:

### Code Review
- [ ] All user-facing strings reviewed
- [ ] All error messages sanitized
- [ ] All success messages reviewed
- [ ] All view templates checked
- [ ] All controller responses checked
- [ ] All JavaScript console.log removed or sanitized

### Asset Review
- [ ] All screenshots are whitelabel
- [ ] All images checked for branding
- [ ] Marketplace.json description is whitelabel
- [ ] App tagline is whitelabel

### Testing
- [ ] Full user journey tested
- [ ] All error scenarios tested
- [ ] Browser console checked
- [ ] Network tab reviewed
- [ ] Mobile view checked

### Documentation
- [ ] Public README is whitelabel (if applicable)
- [ ] Help documentation is whitelabel
- [ ] Support materials are whitelabel

### Automated Checks
- [ ] Ran all grep searches above (zero results in user-facing files)
- [ ] Verified no "HighLevel" in resources/views/
- [ ] Verified no "HighLevel" in app/Http/Controllers/ responses
- [ ] Verified no "leadconnectorhq.com" or "gohighlevel.com" in user-facing content

---

## Common Pitfalls to Avoid

### 1. Error Message Passthrough
**Problem:** Passing API error messages directly to users
```php
// BAD
->with('error', $apiResponse['error']);

// GOOD
->with('error', 'Setup failed. Please contact support.');
Log::error('API error details', $apiResponse);
```

### 2. Debug Information in Production
**Problem:** Console.log or var_dump exposing details
```javascript
// BAD
console.log('HighLevel OAuth response:', response);

// GOOD
// Remove console.log in production or use generic messages
```

### 3. Hardcoded URLs in Templates
**Problem:** Showing HighLevel URLs to users
```blade
{{-- BAD --}}
<a href="https://app.gohighlevel.com">Go to Dashboard</a>

{{-- GOOD --}}
<a href="{{ $platformUrl }}">Go to Dashboard</a>
```

### 4. Screenshot Metadata
**Problem:** Image filenames or EXIF data containing "HighLevel"
```bash
# BAD
screenshot-highlevel-dashboard.png

# GOOD
screenshot-payment-configuration.png
```

---

## Whitelabel Testing Script

Use this script to systematically test whitelabel compliance:

```bash
#!/bin/bash
# whitelabel-test.sh

echo "=== Whitelabel Compliance Test ==="
echo ""

echo "Searching for 'HighLevel' in user-facing files..."
RESULTS=$(grep -r "HighLevel" resources/views/ app/Http/Controllers/ public/ 2>/dev/null)
if [ -z "$RESULTS" ]; then
    echo "✓ No 'HighLevel' found in user-facing files"
else
    echo "✗ Found 'HighLevel' references:"
    echo "$RESULTS"
fi

echo ""
echo "Searching for domain references..."
DOMAINS=$(grep -r -E "(leadconnectorhq\.com|gohighlevel\.com)" resources/views/ 2>/dev/null)
if [ -z "$DOMAINS" ]; then
    echo "✓ No HighLevel domains found in views"
else
    echo "✗ Found domain references:"
    echo "$DOMAINS"
fi

echo ""
echo "Checking for GHL abbreviation..."
GHL=$(grep -r "GHL" resources/views/ 2>/dev/null)
if [ -z "$GHL" ]; then
    echo "✓ No 'GHL' found"
else
    echo "✗ Found 'GHL' references:"
    echo "$GHL"
fi

echo ""
echo "=== Test Complete ==="
```

---

## Sign-Off Checklist

Before marking whitelabel compliance as complete:

- [ ] Product Manager reviewed all user-facing content
- [ ] Developer ran all automated searches (zero violations)
- [ ] QA tested full user journey (no HighLevel references seen)
- [ ] Screenshots verified to be whitelabel-compliant
- [ ] Error scenarios tested (all messages are generic)
- [ ] Marketplace preview checked (description, tagline, images)
- [ ] Second reviewer confirmed whitelabel compliance
- [ ] All issues documented in this checklist are resolved

---

## Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-12-18 | Product Manager | Initial whitelabel checklist created |

---

## Next Steps

1. Use this checklist systematically for each section
2. Document all violations found with file path and line number
3. Fix violations using the terminology guide
4. Re-run automated searches to verify fixes
5. Conduct manual testing
6. Get final approval before resubmission
