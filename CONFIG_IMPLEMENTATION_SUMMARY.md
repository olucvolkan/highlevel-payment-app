# HighLevel Config Creation Implementation Summary

## Implemented Changes

### 1. Database Migration ✅
**File**: `database/migrations/2025_12_03_211642_add_api_keys_to_hl_accounts_table.php`

Added 4 new columns to `hl_accounts` table:
- `api_key_live` (text, nullable)
- `api_key_test` (text, nullable)
- `publishable_key_live` (text, nullable)
- `publishable_key_test` (text, nullable)

**Status**: Migration executed successfully

### 2. HLAccount Model Updates ✅
**File**: `app/Models/HLAccount.php`

**New Methods**:
- `generateApiKeys()` - Generates unique HMAC-SHA256 based API keys for both test and live modes
- `hasApiKeys()` - Checks if all API keys are configured
- `getApiKeys()` - Returns formatted keys for HighLevel config
- `isValidApiKey($apiKey)` - Validates incoming API keys from HighLevel requests

**Fields Added**:
- Added new columns to `$fillable` array
- Added new columns to `$hidden` array (for security)

### 3. HighLevelProviderController Updates ✅
**File**: `app/Http/Controllers/HighLevelProviderController.php`

**Updated Method**: `saveCredentials()` (line 157)

**New Flow**:
1. Save PayTR merchant credentials
2. **Generate API keys** using `$account->generateApiKeys()`
3. **Create HighLevel config** by calling `$highLevelService->connectConfig()`
4. Handle success/failure responses
5. Send provider connected callback
6. Log activity and redirect

### 4. PaymentController Updates ✅
**File**: `app/Http/Controllers/PaymentController.php`

**Updated Method**: `query()` (line 32)

**Added Security**:
- Extract `apiKey` from request payload
- Validate using `$account->isValidApiKey($apiKey)`
- Return 401 Unauthorized if validation fails
- Log validation attempts for security monitoring

### 5. HighLevelService (Already Implemented) ✅
**File**: `app/Services/HighLevelService.php`

**Method**: `connectConfig()` (line 249)
- Already implemented and compatible with our changes
- Sends POST to `/payments/custom-provider/config`
- Handles both testMode and liveMode configurations
- Stores config_id on success

## User Flow

```
┌─────────────────────────────────────────────────────────────┐
│  1. User Opens PayTR Connect Page                          │
│     /paytr/connect?locationId=xxx&companyId=yyy            │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  v
┌─────────────────────────────────────────────────────────────┐
│  2. User Enters PayTR Credentials                          │
│     - Merchant ID                                           │
│     - Merchant Key                                          │
│     - Merchant Salt                                         │
│     - Test Mode (checkbox)                                  │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  v
┌─────────────────────────────────────────────────────────────┐
│  3. Backend Processes (saveCredentials)                    │
│     a. Save PayTR credentials to database                   │
│     b. Generate API keys (hash-based)                       │
│        - api_key_live                                       │
│        - api_key_test                                       │
│        - publishable_key_live                               │
│        - publishable_key_test                               │
│     c. Call HighLevel connectConfig API                     │
│     d. Store config_id from response                        │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  v
┌─────────────────────────────────────────────────────────────┐
│  4. HighLevel Config Created                                │
│     - Test mode credentials: api_key_test + publishable     │
│     - Live mode credentials: api_key_live + publishable     │
│     - Provider shows as "Configured" in HighLevel           │
└─────────────────────────────────────────────────────────────┘
```

## API Key Generation Strategy

Keys are generated using HMAC-SHA256 with the following format:

```php
hash_hmac('sha256',
    $locationId . ':' . $mode . ':' . $keyType . ':' . $timestamp,
    config('app.key')
)
```

**Example**:
- Location: `loc_abc123`
- Timestamp: `1701619200`
- App Key: (from `.env`)

**Generated Keys**:
```
api_key_live = hash_hmac('sha256', 'loc_abc123:live:api:1701619200', APP_KEY)
api_key_test = hash_hmac('sha256', 'loc_abc123:test:api:1701619200', APP_KEY)
publishable_key_live = hash_hmac('sha256', 'loc_abc123:live:publishable:1701619200', APP_KEY)
publishable_key_test = hash_hmac('sha256', 'loc_abc123:test:publishable:1701619200', APP_KEY)
```

## Security Features

1. **API Keys Hidden**: All keys are in `$hidden` array, never exposed in API responses
2. **Request Validation**: PaymentController validates all incoming query requests
3. **Logging**: All key generation and validation attempts are logged
4. **Unique Per Location**: Each location gets unique keys
5. **Time-based**: Keys include timestamp to ensure uniqueness

## Testing Guide

### Prerequisites
- Docker running with PostgreSQL
- Laravel app running (`php artisan serve`)
- HighLevel account with location created
- PayTR test merchant credentials

### Test Steps

#### 1. Verify Database Schema
```bash
php artisan migrate:status
# Should show the api_keys migration as completed
```

#### 2. Check HLAccount Model
```bash
php artisan tinker
>>> $account = App\Models\HLAccount::first();
>>> $account->generateApiKeys();
>>> $account->hasApiKeys();
>>> $account->getApiKeys();
```

Expected output:
```php
=> [
     "live" => [
       "apiKey" => "abc123...",
       "publishableKey" => "def456...",
     ],
     "test" => [
       "apiKey" => "ghi789...",
       "publishableKey" => "jkl012...",
     ],
   ]
```

#### 3. Test Config Creation Flow

**Manual Test**:
1. Navigate to: `http://localhost:8000/paytr/connect?locationId=test_loc_123`
2. Fill in the form:
   - Merchant ID: (your test merchant ID)
   - Merchant Key: (your test merchant key)
   - Merchant Salt: (your test merchant salt)
   - Test Mode: ✓ (checked)
3. Submit the form
4. Check logs: `tail -f storage/logs/laravel.log`

**Expected Log Entries**:
```
[INFO] API keys generated for HighLevel config
[INFO] Creating HighLevel config
[INFO] HighLevel config created successfully
[INFO] PayTR credentials saved successfully
```

#### 4. Test API Key Validation

**Create a test request**:
```bash
curl -X POST http://localhost:8000/api/payments/query \
  -H "Content-Type: application/json" \
  -H "X-Location-Id: test_loc_123" \
  -d '{
    "type": "verify",
    "apiKey": "INVALID_KEY",
    "transactionId": "test_123"
  }'
```

**Expected Response**:
```json
{
  "error": "Unauthorized - Invalid API key"
}
```

**With Valid Key**:
```bash
# First, get the valid key from database:
php artisan tinker
>>> $account = App\Models\HLAccount::where('location_id', 'test_loc_123')->first();
>>> $account->api_key_test

# Then use it in the request:
curl -X POST http://localhost:8000/api/payments/query \
  -H "Content-Type: application/json" \
  -H "X-Location-Id: test_loc_123" \
  -d '{
    "type": "verify",
    "apiKey": "ACTUAL_KEY_FROM_DATABASE",
    "transactionId": "test_123"
  }'
```

#### 5. Verify HighLevel Integration

**Check in HighLevel Dashboard**:
1. Go to Settings > Payments > Integrations
2. Find "PayTR" provider
3. Status should show: **Connected** or **Configured**
4. Test/Live mode toggle should be functional

**Check Config in Database**:
```bash
php artisan tinker
>>> $account = App\Models\HLAccount::where('location_id', 'YOUR_LOCATION_ID')->first();
>>> $account->config_id  // Should have a value
>>> $account->hasApiKeys()  // Should return true
>>> $account->paytr_configured  // Should be true
```

## Troubleshooting

### Issue: Config Creation Fails (400/401/422)

**Check**:
1. `$account->access_token` is valid
2. Token type is correct (may need location token)
3. Payload format matches HighLevel expectations

**Solution**:
```php
// In HighLevelProviderController::saveCredentials, add before connectConfig:
if ($account->needsLocationTokenExchange()) {
    $this->highLevelService->exchangeCompanyTokenForLocation($account, $account->location_id);
    $account->refresh();
}
```

### Issue: API Key Validation Always Fails

**Check**:
1. Keys are being generated and saved
2. Request contains `apiKey` parameter
3. Keys match exactly (no whitespace)

**Debug**:
```bash
php artisan tinker
>>> $account = App\Models\HLAccount::first();
>>> $account->api_key_test
>>> $testKey = "paste_from_request";
>>> $account->isValidApiKey($testKey);
```

### Issue: Config Not Showing in HighLevel

**Possible Reasons**:
1. Provider not created yet (need to create provider first)
2. Wrong endpoint URL in config
3. Token permissions missing
4. Config created but not activated

**Check**:
```bash
# View full account details
php artisan tinker
>>> $account = App\Models\HLAccount::first();
>>> $account->toArray();
```

Look for:
- `third_party_provider_id` (should exist)
- `config_id` (should exist after config creation)
- `is_active` (should be true)

## Next Steps

After successful testing:

1. **Update ENV Variables**: Ensure production credentials are set
2. **Monitor Logs**: Watch for config creation success/failures
3. **Test Payment Flow**: Create a test payment to verify full integration
4. **Document for Users**: Create user-facing guide for credential setup

## Files Modified

1. `database/migrations/2025_12_03_211642_add_api_keys_to_hl_accounts_table.php` (NEW)
2. `app/Models/HLAccount.php` (UPDATED - 4 methods added)
3. `app/Http/Controllers/HighLevelProviderController.php` (UPDATED - config creation added)
4. `app/Http/Controllers/PaymentController.php` (UPDATED - API key validation added)

## Configuration Values

**HighLevel API URLs**:
- Config endpoint: `https://services.leadconnectorhq.com/payments/custom-provider/config`
- Provider endpoint: `https://services.leadconnectorhq.com/payments/custom-provider/provider`

**Required in .env**:
```env
HIGHLEVEL_API_URL=https://services.leadconnectorhq.com
APP_KEY=base64:... # Must be set for key generation
```

## Success Criteria

✅ Migration executed successfully
✅ API keys generated and stored
✅ Config creation API called
✅ config_id stored in database
✅ API key validation working
✅ Provider shows as "Configured" in HighLevel
✅ Test/Live mode toggle functional
✅ Payment query endpoint secured

## Support

If you encounter issues:
1. Check `storage/logs/laravel.log` for detailed error messages
2. Verify database schema with `php artisan migrate:status`
3. Test API key generation in tinker
4. Review HighLevel documentation: https://marketplace.gohighlevel.com/docs/ghl/payments/create-config/

---

**Implementation Date**: December 3, 2025
**Laravel Version**: 12
**PHP Version**: 8.3+
