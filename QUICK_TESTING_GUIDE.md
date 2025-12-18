# Quick Testing Guide - OAuth Fix

## What Was Fixed

**Problem:** HighLevel OAuth installation failed with "Location not found" (400 error)

**Root Causes:**
1. ❌ Code was sending `companyId` + `locationId` to `/oauth/locationToken` endpoint
2. ❌ Location extraction logic confused `companyId` with `locationId`
3. ❌ Code required `company_id` to be set before token exchange (but HighLevel doesn't provide it initially)

**Solution:**
1. ✅ Now only sends `locationId` (HighLevel infers companyId from Bearer token)
2. ✅ Location extraction only looks for location-specific fields
3. ✅ Removed company_id requirement for token exchange

## Quick Test Commands

### 1. Start the App
```bash
# Terminal 1 - Start database
docker-compose up -d

# Terminal 2 - Start Laravel
php artisan serve

# Terminal 3 - Watch logs (keep this open)
tail -f storage/logs/laravel.log
```

### 2. Test OAuth Flow

**Option A: Via HighLevel Marketplace (Recommended)**
1. Go to your HighLevel test account
2. Navigate to: Settings → Integrations → Marketplace
3. Find your app and click "Install"
4. Watch the logs in Terminal 3

**Option B: Via Direct URL (Manual Testing)**
```bash
# Get the OAuth URL from your app
curl http://localhost:8000/oauth/authorize
# Follow the redirect to HighLevel
```

### 3. Check Logs for Success

Watch Terminal 3 for these SUCCESS indicators:

```
✅ [DEBUG] OAuth callback started
✅ [DEBUG] Location ID extraction completed - extracted_id: "loc_xxx"
✅ [DEBUG] Exchanging Company token for Location token
✅ [DEBUG] Sending location token exchange request - form_params: {"locationId":"loc_xxx"}
✅ [DEBUG] Location token exchange API response - status_code: 200
✅ [DEBUG] Account updated with location token
✅ OAuth callback completed with Location token
```

**CRITICAL**: Check that `form_params` shows ONLY `locationId`, NOT `companyId`!

### 4. Verify in Database

```bash
# Connect to database
docker-compose exec postgres psql -U laravel -d highlevel_payments

# Check the account
\x
SELECT
    id,
    location_id,
    company_id,
    token_type,
    CASE WHEN location_access_token IS NOT NULL THEN 'SET' ELSE 'NULL' END as loc_token,
    CASE WHEN company_access_token IS NOT NULL THEN 'SET' ELSE 'NULL' END as comp_token,
    is_active,
    created_at
FROM hl_accounts
ORDER BY created_at DESC
LIMIT 1;
```

**Expected:**
- `token_type`: Location
- `loc_token`: SET
- `comp_token`: SET
- `is_active`: t

### 5. Run Automated Tests

```bash
# Run the OAuth token exchange test suite
php artisan test --filter=OAuthLocationTokenExchangeTest

# Should see:
# ✓ token exchange request only includes location id
# ✓ location id extraction excludes company id
# ✓ token exchange handles 400 error gracefully
# ✓ token exchange requires location id
# ✓ token exchange works without company id
# ✓ location id validation
# ✓ complete oauth flow with token exchange
```

## Common Issues

### Issue: "Company ID is required"
**Status:** ❌ This error should NOT appear anymore (this was the bug!)
**Action:** If you see this, the fix wasn't applied correctly. Check git status.

### Issue: "Location not found" (400)
**Before Fix:** Common error
**After Fix:** Should be rare (only if location truly doesn't exist)

**Debug:**
```bash
# Check what was sent in the request
grep "Sending location token exchange request" storage/logs/laravel.log | tail -1

# Should show: form_params: {"locationId":"..."}
# Should NOT show: form_params: {"companyId":"...","locationId":"..."}
```

### Issue: "Location ID not found in OAuth response"
**Cause:** HighLevel callback missing location_id parameter
**Debug:**
```bash
grep "Location ID extraction completed" storage/logs/laravel.log | tail -1
# Check: available_query_params and available_token_keys
```

## Rollback

If you need to revert:

```bash
git log --oneline -5  # Find the commit before the fix
git revert <commit-hash>
php artisan config:clear
php artisan cache:clear
```

## Success Checklist

Before marking this as "fixed":

- [ ] OAuth installation completes without errors
- [ ] No "Location not found" (400) errors in logs
- [ ] `location_access_token` is saved in database
- [ ] Token exchange request only includes `locationId`
- [ ] Location ID extraction doesn't use `companyId`
- [ ] All automated tests pass
- [ ] Can create payment provider after OAuth

## Production Deployment

### Pre-Deployment
```bash
# Backup database
docker-compose exec postgres pg_dump -U laravel highlevel_payments > backup_$(date +%Y%m%d).sql

# Run tests
php artisan test

# Check for syntax errors
php artisan config:clear
php artisan cache:clear
php artisan optimize
```

### Deploy
```bash
git add .
git commit -m "Fix OAuth location token exchange bug

- Remove company_id requirement for token exchange
- Only send locationId in token exchange request (not companyId)
- Fix location ID extraction to exclude company_id
- Add comprehensive error handling and validation
- Add debug logging throughout OAuth flow

Fixes: 400 'Location not found' error during OAuth installation"

git push origin master
```

### Post-Deployment Monitoring
```bash
# Monitor errors (first 30 minutes)
tail -f storage/logs/laravel.log | grep -i error

# Check success rate (after 24 hours)
grep "OAuth callback completed" storage/logs/laravel.log | wc -l
grep "Token exchange failed" storage/logs/laravel.log | wc -l
# Success rate should be > 95%
```

## Support

If issues persist after applying this fix:

1. **Collect Debug Info:**
   ```bash
   # Get last OAuth attempt logs
   grep -A 50 "OAuth callback started" storage/logs/laravel.log | tail -60 > debug.txt
   ```

2. **Check Request Format:**
   - Verify `form_params` only has `locationId`
   - Confirm Authorization header is present
   - Check location_id is not a company_id

3. **Contact with:**
   - Full error message
   - HighLevel traceId (if provided)
   - Relevant log excerpts
   - Database state of affected account

---

**Quick Reference - What Changed:**

| Before (Bug) | After (Fixed) |
|--------------|---------------|
| Requires `company_id` to be set | No `company_id` requirement |
| Sends `companyId` + `locationId` | Only sends `locationId` |
| Extracts from `companyId` fields | Only extracts from `locationId` fields |
| Generic error messages | User-friendly, actionable errors |
| Limited debug logging | Comprehensive debug logging |

**Key Insight:** HighLevel's `/oauth/locationToken` endpoint infers the `companyId` from the Company access token in the Authorization header. Explicitly sending it causes errors!
