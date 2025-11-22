# Demo Testing Guide - HighLevel PayTR Integration

> **Purpose**: Step-by-step guide for recording demo video
> **Duration**: ~15-20 minutes
> **Audience**: HighLevel users, potential clients
> **Status**: Production Ready ✅

**Production URL**: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud`

## Pre-Demo Checklist (3 minutes)

### 1. Production Deployment Status
```bash
# Application is deployed on Laravel Cloud
# No local setup needed for demo!
# URL: https://yerelodeme-payment-app-master-a645wy.laravel.cloud
```

### 2. Verify Production Health
```bash
# Test API health
curl https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/health

# Expected response:
# {"status":"ok","service":"HighLevel PayTR Integration","timestamp":"...","version":"1.0.0"}
```

### 3. Demo Test Account (Pre-created)
```
Location ID: demo_loc_20251122
Company ID: demo_company
Status: Active ✅
```

### 4. Test Credentials (PayTR)
Use your PayTR test credentials or demo mode:
```
Merchant ID: [YOUR_TEST_MERCHANT_ID]
Merchant Key: [YOUR_TEST_KEY]
Merchant Salt: [YOUR_TEST_SALT]
Test Mode: ENABLED ✅
```

---

## Demo Script (15 minutes)

### Part 1: Landing Page (2 minutes)

**Objective**: Show the public-facing landing page

**URL**: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud/`

**Actions**:
1. Open browser
2. Navigate to production landing page
3. Show:
   - Hero section ("Türkiye'nin İlk HighLevel PayTR Entegrasyonu")
   - Features (6 key features)
   - Payment providers (PayTR highlighted, Iyzico coming soon)
   - How it works (3 steps: Install → Configure → Accept Payments)
   - Call-to-action

**Script**:
> "This is the landing page that HighLevel users see when they discover our PayTR integration in the marketplace. It highlights the key features: multi-tenant support, secure credential storage, OAuth integration, and comprehensive logging. The integration is production-ready and deployed on Laravel Cloud for high availability."

---

### Part 2: PayTR Setup (4 minutes)

**Objective**: Configure PayTR credentials

**URL**: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud/paytr/setup?location_id=demo_loc_20251122`

**Actions**:
1. Navigate to setup page
2. Show empty configuration form
3. Fill in credentials:
   - Merchant ID: `test_merchant`
   - Merchant Key: `test_key_123`
   - Merchant Salt: `test_salt_456`
   - Test Mode: ✓ Checked
4. Click "Test Credentials"
5. Show success message (or explain API validation)
6. Click "Save Configuration"
7. Show saved configuration display

**Script**:
> "Here's where agencies configure their PayTR merchant credentials. We validate the credentials before saving by making a test API call to PayTR. All sensitive data is encrypted in the database using Laravel's encryption. Each HighLevel location has its own isolated credentials."

**Demo Points**:
- Security: credentials encrypted
- Validation: test before save
- Multi-tenant: per-location config
- Test mode support

---

### Part 3: Payment Initialization (3 minutes)

**Objective**: Show payment iframe page

**URL**:
```
https://yerelodeme-payment-app-master-a645wy.laravel.cloud/payments/page?locationId=DEMO_LOC_ID&transactionId=demo_txn_001&amount=100.00&currency=TRY&email=demo@example.com&contactId=demo_contact
```

**Actions**:
1. Navigate to payment page URL
2. Show loading state
3. Explain what's happening:
   - Creating Payment record
   - Generating HMAC-SHA256 signature
   - Calling PayTR API
   - Receiving iframe token
   - Rendering iframe
4. Show PayTR iframe (if test credentials work)
5. Explain test cards if available

**Script**:
> "This is the payment page that gets embedded in HighLevel's iframe. When a customer initiates payment, we:
> 1. Create a secure Payment record in our database
> 2. Generate an HMAC-SHA256 signature using the merchant's credentials
> 3. Call PayTR's API to get an iframe token
> 4. Render the PayTR payment form inside the iframe
> 5. Send postMessage events to HighLevel to update the parent window"

**Show in Browser Console**:
```javascript
// postMessage events
{
  type: 'custom_provider_ready',
  data: {
    merchantOid: 'ORDER_xxx',
    transactionId: 'demo_txn_001'
  }
}
```

---

### Part 4: API Endpoints (4 minutes)

**Objective**: Demonstrate API functionality

#### A. Health Check
```bash
curl https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/health
```

**Expected Response**:
```json
{
  "status": "ok",
  "service": "HighLevel PayTR Integration",
  "timestamp": "2025-11-22T10:00:00Z",
  "version": "1.0.0"
}
```

#### B. System Status
```bash
curl https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/status
```

**Expected Response**:
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
  }
}
```

#### C. Payment Query (Verify)
```bash
curl -X POST https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/payments/query \
  -H "Content-Type: application/json" \
  -H "X-Location-Id: DEMO_LOC_ID" \
  -d '{
    "type": "verify",
    "transactionId": "demo_txn_001",
    "chargeId": "ORDER_xxx"
  }'
```

**Script**:
> "These are the API endpoints that HighLevel calls:
> - /api/health - For uptime monitoring
> - /api/status - System health check
> - /api/payments/query - The main endpoint for all payment operations: verify, list cards, charge, refund"

---

### Part 5: Database & Logging (2 minutes)

**Objective**: Show data persistence and audit trail

**Actions**:
1. Open database tool (TablePlus, pgAdmin, or psql)
2. Show tables:
   - `hl_accounts` - Demo account with encrypted credentials
   - `payments` - Payment records
   - `webhook_logs` - Webhook history
   - `user_activity_logs` - Audit trail

**SQL Examples**:
```sql
-- Show demo account
SELECT location_id, paytr_merchant_id, paytr_configured, paytr_test_mode, created_at
FROM hl_accounts
WHERE location_id = 'DEMO_LOC_ID';

-- Show payments for demo account
SELECT merchant_oid, transaction_id, amount, currency, status, created_at
FROM payments
WHERE location_id = 'DEMO_LOC_ID'
ORDER BY created_at DESC
LIMIT 5;

-- Show webhook logs
SELECT source, event_type, status_code, processed, created_at
FROM webhook_logs
ORDER BY created_at DESC
LIMIT 5;

-- Show user activity
SELECT action, description, created_at
FROM user_activity_logs
WHERE location_id = 'DEMO_LOC_ID'
ORDER BY created_at DESC
LIMIT 5;
```

**Script**:
> "All data is persisted in PostgreSQL with full audit trails:
> - Every payment transaction is logged
> - All webhooks (incoming and outgoing) are stored
> - User actions are tracked for compliance
> - Sensitive credentials are encrypted at rest"

---

## Advanced Demo (Optional - 5 minutes)

### OAuth Flow Simulation

If you want to show the OAuth flow:

1. Visit: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud/docs`
2. Show integration URLs:
   - Query URL
   - Payments URL
   - Redirect URI
   - Webhook URL

3. Explain OAuth process:
   ```
   User clicks Install in HighLevel
       ↓
   Redirected to HighLevel OAuth
       ↓
   User authorizes app
       ↓
   Redirected back with code
       ↓
   Exchange code for tokens
       ↓
   Store in database
       ↓
   Create payment integration
       ↓
   Redirect to PayTR setup
   ```

4. Show `hl_accounts` table with OAuth tokens (don't expose actual tokens)

---

## Testing Checklist

Before recording:
- [ ] Production application is live at Laravel Cloud
- [ ] Demo account (`demo_loc_20251122`) is active in database
- [ ] PayTR credentials available (or explain test mode)
- [ ] All production endpoints respond correctly
- [ ] Browser DevTools ready for console inspection
- [ ] Screen recording software ready

---

## Key Points to Emphasize

### 1. Security
- Credentials encrypted at rest
- HMAC-SHA256 signature verification
- Multi-tenant data isolation
- No sensitive data in logs

### 2. Architecture
- Service layer pattern
- Strategy pattern for multiple providers
- Repository pattern for data access
- Clean separation of concerns

### 3. Features
- Multi-tenant support (per HighLevel location)
- Comprehensive logging and audit trails
- Webhook integration (PayTR ↔ HighLevel)
- Card storage for recurring payments
- Refund processing
- Test mode support

### 4. HighLevel Integration
- OAuth 2.0 authentication
- Custom payment provider API
- iframe embedding
- postMessage communication
- Webhook notifications

---

## Troubleshooting During Demo

### Issue: Production application not responding
**Solution**:
- Check Laravel Cloud deployment status
- Verify environment variables are set correctly
- Check application logs in Laravel Cloud dashboard

### Issue: Database connection error
**Solution**:
```bash
# Check .env database settings
# Test connection:
php artisan tinker
DB::connection()->getPdo();
```

### Issue: PayTR iframe doesn't load
**Solution**:
- Check if test credentials are valid
- Verify merchant_id, merchant_key, merchant_salt
- Check Laravel logs: `tail -f storage/logs/laravel.log`
- Show simulated iframe instead

### Issue: CSRF token mismatch
**Solution**:
```bash
# Clear cache
php artisan config:clear
php artisan cache:clear
# Refresh browser
```

---

## Post-Demo Cleanup

```bash
# Optional: Delete demo account via Tinker
php artisan tinker
```

```php
$account = \App\Models\HLAccount::where('location_id', 'demo_loc_20251122')->first();
if ($account) {
    $account->delete();
    echo "Demo account deleted\n";
}
exit;
```

**Note**: Production application continues running on Laravel Cloud. No local services to stop.

---

## URL Templates

Production URLs ready to use (replace `DEMO_LOC_ID` with your test location ID):

```
Landing Page:
https://yerelodeme-payment-app-master-a645wy.laravel.cloud/

PayTR Setup:
https://yerelodeme-payment-app-master-a645wy.laravel.cloud/paytr/setup?location_id=DEMO_LOC_ID

Payment Page:
https://yerelodeme-payment-app-master-a645wy.laravel.cloud/payments/page?locationId=DEMO_LOC_ID&transactionId=demo_txn_001&amount=100.00&currency=TRY&email=demo@example.com&contactId=demo_contact

API Documentation:
https://yerelodeme-payment-app-master-a645wy.laravel.cloud/docs

Health Check:
https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/health

System Status:
https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/status
```

---

## Recording Tips

1. **Use high-quality screen recording** (1080p minimum)
2. **Clear browser history** before recording (clean URL bar)
3. **Close unnecessary tabs** (reduce distractions)
4. **Prepare script** (practice once before recording)
5. **Show terminal and browser** side-by-side when relevant
6. **Zoom in** on important parts (credentials form, API responses)
7. **Speak slowly and clearly** (assume non-technical audience)
8. **Use cursor highlighting** software for visibility
9. **Record in quiet environment**
10. **Keep video under 20 minutes** (ideal: 12-15 minutes)

---

## Demo Flow Summary

```
1. Landing Page (2 min)
   - Show features and value proposition

2. PayTR Setup (4 min)
   - Configure credentials
   - Test and save
   - Show encrypted storage

3. Payment Page (3 min)
   - Initialize payment
   - Show iframe
   - Explain flow

4. API Endpoints (4 min)
   - Health check
   - Payment query
   - Show responses

5. Database & Logs (2 min)
   - Show data persistence
   - Audit trails
   - Multi-tenant isolation

TOTAL: ~15 minutes
```

---

## Success Criteria

Your demo is successful if you show:
- ✅ Complete OAuth → Setup → Payment flow
- ✅ Secure credential management
- ✅ API functionality (at least 3 endpoints)
- ✅ Database persistence
- ✅ Multi-tenant architecture
- ✅ Professional UI/UX
- ✅ Error handling (bonus)
- ✅ Logging and monitoring

---

**Good luck with your demo! 🚀**

For questions, check:
- `.agent/readme.md` - Quick navigation
- `.agent/system/` - Technical details
- `.agent/SOPs/` - Step-by-step procedures
