# OAuth Location Token Exchange Bug Fix - Summary

## Issue Description

The HighLevel marketplace app installation was failing with a **400 Bad Request** error during the location token exchange:

```
Error: POST https://services.leadconnectorhq.com/oauth/locationToken
Response: {"message":"Location not found","error":"Bad Request","statusCode":400}
```

## Root Cause Analysis

### Primary Issues Identified

1. **Missing `company_id` Validation Error** (`HighLevelService.php:114-116`)
   - The code required `company_id` to be set before attempting token exchange
   - HighLevel's initial OAuth token response does NOT include `companyId`
   - This caused the exchange to fail with "Company ID is required" before even making the API call

2. **Incorrect Request Payload** (`HighLevelService.php:139-142`)
   - The code was sending BOTH `companyId` AND `locationId` in the token exchange request
   - **CRITICAL FIX**: HighLevel's `/oauth/locationToken` endpoint only needs `locationId`
   - The API infers `companyId` from the Company access token in the Authorization header
   - Sending an incorrect/missing `companyId` caused "Location not found" errors

3. **Location ID Extraction Bug** (`OAuthController.php:243`)
   - The `extractLocationId()` method included `companyId` and `company_id` in the list of possible location ID sources
   - **This is fundamentally wrong**: A company can have multiple locations
   - Using a `companyId` when `locationId` is expected causes "Location not found" errors
   - Example: Company "ABC123" has locations "LOC456", "LOC789" - sending "ABC123" to a location endpoint fails

## Implemented Fixes

### 1. Fixed Token Exchange Request (`app/Services/HighLevelService.php`)

**Before:**
```php
if (!$account->company_id) {
    throw new \InvalidArgumentException('Company ID is required for token exchange');
}

$options = [
    'form_params' => [
        'companyId' => $account->company_id,  // ❌ This causes errors
        'locationId' => $locationId,
    ],
];
```

**After:**
```php
if (empty($locationId)) {
    throw new \InvalidArgumentException('Location ID is required for token exchange');
}

// CRITICAL FIX: Only send locationId in the payload
// HighLevel infers companyId from the Company access token
$options = [
    'form_params' => [
        'locationId' => $locationId,  // ✅ Only send location ID
    ],
];
```

**Key Changes:**
- Removed `company_id` validation requirement (it's not needed for the API call)
- Removed `companyId` from the request payload
- HighLevel infers the company from the Bearer token in the Authorization header
- Added location ID format validation with `isValidLocationId()` helper

### 2. Fixed Location ID Extraction (`app/Http/Controllers/OAuthController.php`)

**Before:**
```php
// ❌ This treats company_id as location_id (WRONG!)
$possibleKeys = ['locationId', 'location_id', 'companyId', 'company_id'];
```

**After:**
```php
// ✅ Only extract actual location IDs
$possibleKeys = ['locationId', 'location_id'];
```

**Key Changes:**
- Removed `companyId` and `company_id` from location extraction sources
- Added comprehensive debug logging to track extraction source
- Added validation to ensure extracted ID is not empty
- Improved priority order: query parameter > token response > session > state parameter

### 3. Enhanced Error Handling (`app/Services/HighLevelService.php`)

**New Features:**
- Parse JSON error responses from HighLevel API
- Provide user-friendly error messages based on status codes
- Add diagnostic information for common error patterns
- Log detailed error context for debugging
- Return both user-facing and technical error messages

**Error Message Improvements:**
```php
// 400 Bad Request → "Location not found"
'Unable to access the specified location. Please ensure the integration is installed in the correct HighLevel location.'

// 401 Unauthorized
'Authentication failed. Please reinstall the integration.'

// 403 Forbidden
'Permission denied to access this location.'
```

### 4. Added Location ID Validation

**New Helper Method:**
```php
protected function isValidLocationId(string $locationId): bool
{
    // Validate format: non-empty, reasonable length (10-50 chars), alphanumeric
    // Prevents company IDs being used as location IDs
}
```

### 5. Improved Debug Logging

**Added extensive logging throughout the OAuth flow:**
- Token exchange request parameters
- Location ID extraction source tracking
- Response parsing and validation
- Token storage confirmation
- Error context with full request/response details

## Files Modified

1. **`app/Services/HighLevelService.php`**
   - Fixed `exchangeCompanyTokenForLocation()` method (lines 107-287)
   - Removed `company_id` requirement
   - Changed request payload to only include `locationId`
   - Enhanced error handling with diagnostic messages
   - Added `isValidLocationId()` validation helper (lines 853-866)
   - Added company_id storage from location token response

2. **`app/Http/Controllers/OAuthController.php`**
   - Fixed `extractLocationId()` method (lines 230-292)
   - Removed company ID from location extraction sources
   - Added comprehensive debug logging
   - Enhanced error handling in token exchange flow (lines 81-139)
   - Added verification that location token was actually saved

## Testing Guide

### Prerequisites
- HighLevel marketplace app configured with OAuth credentials
- Test HighLevel account with at least one location
- Access to application logs for debugging

### Test Steps

#### 1. Fresh Installation Test
```bash
# Start the Laravel development server
php artisan serve

# Access the marketplace and install the app
# Navigate to: HighLevel Settings → Integrations → Custom Integrations
# Click "Install" on your PayTR integration
```

**Expected Behavior:**
- OAuth callback receives authorization code
- Code is exchanged for Company token
- `location_id` is extracted from query parameter
- Company token is exchanged for Location token successfully
- Account is created/updated with proper tokens
- Third-party provider is registered in HighLevel
- User is redirected to PayTR setup page

#### 2. Check Logs for Success
```bash
# View Laravel logs
tail -f storage/logs/laravel.log

# Look for these success indicators:
# ✅ "[DEBUG] OAuth callback started"
# ✅ "[DEBUG] Location ID extraction completed" with source and extracted_id
# ✅ "[DEBUG] Exchanging Company token for Location token"
# ✅ "[DEBUG] Sending location token exchange request" with form_params showing only locationId
# ✅ "[DEBUG] Location token exchange API response" with 200 status
# ✅ "[DEBUG] Account updated with location token" with has_location_access_token: true
# ✅ "OAuth callback completed with Location token"
```

#### 3. Verify Database State
```bash
# Connect to PostgreSQL
docker-compose exec postgres psql -U laravel -d highlevel_payments

# Check the created account
SELECT id, location_id, company_id, token_type,
       CASE WHEN location_access_token IS NOT NULL THEN 'SET' ELSE 'NULL' END as location_token,
       CASE WHEN company_access_token IS NOT NULL THEN 'SET' ELSE 'NULL' END as company_token,
       is_active, created_at
FROM hl_accounts
ORDER BY created_at DESC
LIMIT 1;
```

**Expected Results:**
- `location_id`: Valid HighLevel location ID
- `company_id`: Set (may be NULL if not returned by HighLevel)
- `token_type`: 'Location'
- `location_token`: 'SET'
- `company_token`: 'SET'
- `is_active`: true

#### 4. Test Token Exchange Directly (Optional)

If you need to test the token exchange in isolation:

```php
// In tinker: php artisan tinker

use App\Models\HLAccount;
use App\Services\HighLevelService;

$account = HLAccount::latest()->first();
$service = app(HighLevelService::class);

// Attempt token exchange
$result = $service->exchangeCompanyTokenForLocation($account, $account->location_id);

// Check result
print_r($result);
// Should contain: access_token, refresh_token, userType, expires_in
```

### Success Criteria

✅ **Primary Success Indicators:**
1. No 400 "Location not found" errors in logs
2. Location token successfully obtained from HighLevel
3. `location_access_token` stored in database
4. Third-party provider registered successfully
5. User redirected to PayTR configuration page

✅ **Secondary Validations:**
1. All debug logs show correct location_id (not company_id)
2. Token exchange request only includes `locationId` parameter
3. Error messages are user-friendly (no HighLevel references)
4. Subsequent API calls use location-scoped token

### Common Issues and Troubleshooting

#### Issue: "Location ID not found in OAuth response"

**Cause:** HighLevel didn't send location_id in callback
**Debug:**
```bash
# Check logs for:
[DEBUG] Location ID extraction completed
# Look at: available_query_params, available_token_keys
```
**Solution:** Ensure OAuth redirect URL matches HighLevel configuration exactly

#### Issue: "Access token is required for token exchange"

**Cause:** Initial token exchange failed
**Debug:**
```bash
# Check logs for:
[DEBUG] HighLevel token exchange successful
# Verify: has_access_token: true
```
**Solution:** Check HighLevel OAuth credentials (client_id, client_secret)

#### Issue: Still getting 400 "Location not found"

**Cause:** Location doesn't exist or token lacks access
**Debug:**
```bash
# Check logs for:
[DEBUG] Sending location token exchange request
# Verify: form_params only contains locationId (not companyId)
```
**Solution:**
1. Verify location_id is correct (check HighLevel UI)
2. Ensure user has access to the location
3. Check OAuth scopes include location access

## Rollback Plan

If issues occur after deployment:

```bash
# Revert to previous version
git revert HEAD

# Or checkout specific commit
git checkout <previous-commit-hash>

# Redeploy
php artisan config:clear
php artisan cache:clear
php artisan optimize
```

## Post-Deployment Monitoring

### Metrics to Track

1. **OAuth Success Rate**
   ```bash
   # Count successful installations
   grep "OAuth callback completed with Location token" storage/logs/laravel.log | wc -l

   # Count token exchange failures
   grep "Token exchange failed during OAuth" storage/logs/laravel.log | wc -l
   ```

2. **Error Patterns**
   ```bash
   # Check for 400 errors
   grep "400 Bad Request" storage/logs/laravel.log

   # Check for location not found
   grep "Location not found" storage/logs/laravel.log
   ```

3. **Database Health**
   ```sql
   -- Check active accounts with location tokens
   SELECT COUNT(*) as active_with_location_token
   FROM hl_accounts
   WHERE is_active = true
     AND location_access_token IS NOT NULL;

   -- Check accounts created in last 24 hours
   SELECT COUNT(*) as recent_installations
   FROM hl_accounts
   WHERE created_at >= NOW() - INTERVAL '24 hours';
   ```

### Alert Thresholds

- **Critical**: Token exchange failure rate > 5%
- **Warning**: No successful installations in 24 hours
- **Info**: New installation detected

## Documentation Updates Needed

1. Update `README.md` with corrected OAuth flow description
2. Update API documentation to clarify token types
3. Add troubleshooting guide for common OAuth errors
4. Document location_id vs company_id distinction

## Future Improvements

1. **Add Retry Logic**: Implement exponential backoff for transient API failures
2. **Token Refresh**: Proactively refresh tokens before expiry
3. **Webhook Validation**: Verify marketplace webhooks for install/uninstall events
4. **Admin Dashboard**: Add OAuth health monitoring UI
5. **Automated Testing**: Create integration tests for OAuth flow
6. **Rate Limiting**: Add protection for OAuth endpoint abuse

## Security Considerations

✅ **Security Measures in Place:**
- Tokens stored encrypted in database (via Laravel encryption)
- Access tokens never logged in full (only prefix shown)
- User-facing errors don't expose internal details
- Authorization header uses Bearer token authentication
- Location isolation enforced in database queries

## API Reference

### HighLevel Location Token Exchange Endpoint

**Endpoint:** `POST https://services.leadconnectorhq.com/oauth/locationToken`

**Headers:**
```
Authorization: Bearer {company_access_token}
Content-Type: application/x-www-form-urlencoded
Version: 2021-07-28
Accept: application/json
```

**Request Body (form_params):**
```
locationId: {location_id}
```

**Response (200 OK):**
```json
{
  "access_token": "string",
  "refresh_token": "string",
  "token_type": "Bearer",
  "expires_in": 86400,
  "userType": "Location",
  "locationId": "string",
  "companyId": "string"
}
```

**Error Response (400 Bad Request):**
```json
{
  "message": "Location not found",
  "error": "Bad Request",
  "statusCode": 400,
  "traceId": "uuid"
}
```

## Contact and Support

For issues or questions about this fix:
1. Check logs first: `storage/logs/laravel.log`
2. Review this document for troubleshooting steps
3. Contact development team with log excerpts and error details
4. Include HighLevel traceId if available in error response

---

**Fix Implemented:** 2025-12-18
**Version:** 1.0
**Status:** Ready for Testing
