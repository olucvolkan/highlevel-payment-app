# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Laravel 12 API application** designed to integrate **PayTR** (Turkish payment gateway) as a **custom payment provider** within the **HighLevel Marketplace**. It enables agencies and businesses in Turkey to process payments directly via PayTR inside their HighLevel CRM.

**Current Status**: Documentation and planning phase. Implementation files do not exist yet.

## System Architecture

### Integration Flow
The system acts as a bridge between **HighLevel** and **PayTR**, handling:
1. OAuth authentication with HighLevel
2. Payment initialization via PayTR iframe
3. Payment callbacks from PayTR
4. Webhook propagation to HighLevel
5. Payment verification and status updates

### Key Components
- **HighLevel**: External CRM platform (marketplace installation endpoint)
- **Laravel App**: Intermediary service handling PayTR API calls and HighLevel webhooks
- **PayTR API**: Payment gateway for Turkish market
- **PostgreSQL**: Stores OAuth credentials, payment transactions, webhook logs, and user activity

### Design Patterns
- **Strategy Pattern**: Multi-provider support (PayTR, Stripe, Iyzico) via `PaymentProviderInterface`
- **Factory Pattern**: `PaymentProviderFactory` dynamically returns the correct payment provider strategy
- **Service Layer**: Business logic centralized in service classes, keeping controllers lightweight

## Development Environment

### Docker Setup
The project uses Docker Compose with PostgreSQL 15:

```bash
# Start services
docker-compose up -d

# Run migrations (when implemented)
php artisan migrate

# Start Laravel development server
php artisan serve
```

Database configuration:
- Host: `localhost:5432`
- Database: `highlevel_payments`
- User: `laravel`
- Password: `secret`

## Planned Directory Structure

```
app/
├── Http/Controllers/
│   └── PaymentController.php
├── Services/
│   └── PaymentService.php
├── Repositories/
│   └── PaymentRepository.php
├── PaymentGateways/
│   ├── PaymentProviderInterface.php
│   ├── PayTRPaymentProvider.php
│   ├── StripePaymentProvider.php
│   ├── IyzicoPaymentProvider.php
│   └── PaymentProviderFactory.php
├── Providers/
│   └── PaymentServiceProvider.php
└── Logging/
    ├── PaymentLogger.php
    ├── WebhookLogger.php
    └── UserActionLogger.php
```

## PayTR Integration Details

### Core API Endpoints

**Payment Initialization**:
```
POST https://www.paytr.com/odeme/api/get-token
```
Returns a token for iframe payment page.

**Payment Status Query**:
```
POST https://www.paytr.com/odeme/durum-sorgu
```

**Refund**:
```
POST https://www.paytr.com/odeme/iade
```

### Hash/Token Generation
PayTR requires HMAC-SHA256 signatures for all requests:

```php
$hash_str = $merchant_id . $user_ip . $merchant_oid . $email .
            $payment_amount . $user_basket . $no_installment .
            $max_installment . $currency . $test_mode . $merchant_salt;
$paytr_token = base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));
```

### Callback Verification
When PayTR sends payment results to your callback URL, verify the hash:

```php
$hash = base64_encode(hash_hmac('sha256',
    $merchant_oid . $merchant_salt . $status . $total_amount,
    $merchant_key, true));
if ($hash !== $_POST['hash']) {
   exit('PAYTR notification failed: invalid hash');
}
// Respond with "OK" to acknowledge
echo "OK";
```

### Card Storage API
PayTR supports storing customer cards for recurring payments:
- **Add Card**: `capi_payment_new_card.php` flow
- **List Cards**: Retrieve saved payment methods
- **Charge Stored Card**: `capi_payment_stored_card.php` flow
- **Delete Card**: Remove saved payment method

## HighLevel Integration Details

### Required OAuth Scopes
```
payments/orders.readonly
payments/orders.write
payments/subscriptions.readonly
payments/transactions.readonly
payments/custom-provider.readonly
payments/custom-provider.write
products.readonly
products/prices.readonly
```

### Configuration URLs
- **Redirect URL**: `/oauth/callback` (OAuth code exchange)
- **Webhook URL**: `/webhooks/marketplace` (app install/uninstall events)
- **Query URL**: `/api/payments/query` (payment verification endpoint)
- **Payments URL**: `/payments/page` (iframe payment page)

### Payment Flow Events

**Frontend → HighLevel** (via postMessage):
- `custom_provider_ready`: Iframe loaded and ready
- `custom_element_success_response`: Payment succeeded with `chargeId`
- `custom_element_error_response`: Payment failed with error description
- `custom_element_close_response`: User cancelled payment

**HighLevel → Backend** (POST to queryUrl):
- `{ type: "verify" }`: Verify payment status
- `{ type: "list_payment_methods" }`: Return saved cards
- `{ type: "charge_payment" }`: Charge a saved card
- `{ type: "create_subscription" }`: Create recurring payment
- `{ type: "refund" }`: Process refund

### Webhook Events (Backend → HighLevel)
Send updates to: `https://backend.leadconnectorhq.com/payments/custom-provider/webhook`

Events:
- `subscription.trialing`
- `subscription.active`
- `subscription.charged`
- `payment.captured`

## Database Schema (Planned)

| Table | Purpose |
|-------|---------|
| `hl_accounts` | HighLevel OAuth credentials per tenant/location |
| `payments` | Transaction records (amount, provider, status, chargeId) |
| `webhook_logs` | All webhook requests and responses |
| `user_activity_logs` | User and system action tracking |
| `payment_failures` | Failed payment records with error reasons |

## Logging Strategy

Structured JSON logs with location-based storage:
- **Payment events**: `storage/logs/payments/YYYY-MM-DD.log`
- **Webhook events**: Database + log file
- **User actions**: Database with JSON metadata

Compatible with Sentry, Logtail, or ELK stack.

## Important Implementation Notes

1. **Hash Security**: All PayTR API calls require proper HMAC-SHA256 signatures. Never expose `merchant_key` or `merchant_salt` to frontend.

2. **Callback Response**: PayTR callback handler MUST respond with "OK" string, otherwise PayTR will retry the callback.

3. **Test Mode**: PayTR supports test mode via `test_mode: 1` parameter. Use separate merchant credentials for test/live.

4. **Currency Support**: PayTR supports TRY, USD, EUR. Amount must be sent in **kuruş/cents** (multiply by 100).

5. **iframe Integration**: Payment page must be HTTPS and iframe-compatible. Use postMessage for communication with HighLevel.

6. **Tenant Isolation**: Each HighLevel location should have isolated data. Use `location_id` for all queries.

## Documentation References

### PayTR Documentation
Located in `technical_documentation/PayTR Direkt API/`:
- **Payment Flow**: `PayTR Direkt API/` (Step 1 & 2)
- **Card Storage**: `PayTR Kart Saklama API/`
- **Refunds**: `PayTR İade API/`
- **Status Query**: `PayTR Mağaza Durum Sorgulama/`
- **Installments**: `PayTR Taksit Oranları Sorgulama Servisi/`

Each folder contains Turkish and English versions with PDF documentation and Node.js/PHP examples.

### HighLevel Documentation
Located in `highlevel-api-documentation/`:
- `technical_workflow.md`: Complete integration roadmap with event flows

### Project Documentation
- `README.md`: Comprehensive technical overview with architecture diagrams (in Turkish)
- `pay_tr.md`: PayTR API flow documentation (in Turkish)
- `highlevel_paytr_documentation.md`: Integration architecture guide (in Turkish)

## Future Extensions

- **Stripe Integration**: Implement `StripePaymentProvider` for international payments
- **Iyzico Integration**: Alternative Turkish payment provider
- **Retry Queue**: Failed webhook retry mechanism with exponential backoff
- **Admin Dashboard**: Payment analytics, error reporting, and monitoring
- **Multi-currency**: Extended currency support beyond TRY

## Security Considerations

1. Store all API keys (`merchant_key`, `merchant_salt`, OAuth tokens) encrypted in database
2. Use environment variables for sensitive configuration
3. Validate all webhook signatures before processing
4. Implement rate limiting on public endpoints
5. Use HTTPS for all external communication
6. Sanitize user input in payment descriptions and metadata
7. Implement proper CORS policies for iframe communication
