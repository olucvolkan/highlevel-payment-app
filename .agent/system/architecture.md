# System Architecture

> **Framework**: Laravel 12
> **PHP Version**: 8.3+
> **Database**: PostgreSQL 15 (Supabase)

## Design Patterns

### 1. Service Layer Pattern
**Purpose**: Separate business logic from controllers

**Implementation**:
- `PaymentService` - Payment operations
- `HighLevelService` - HighLevel API communication
- `PayTRHashService` - Signature generation

**Example**:
```php
class PaymentController {
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function query(Request $request) {
        return $this->paymentService->verifyPayment($transactionId);
    }
}
```

---

### 2. Strategy Pattern
**Purpose**: Multiple payment providers with common interface

**Implementation**:
```php
interface PaymentProviderInterface {
    public function initializePayment(array $data): array;
    public function verifyPayment(string $transactionId): array;
    public function refund(Payment $payment, float $amount): array;
}

class PayTRPaymentProvider implements PaymentProviderInterface { }
class IyzicoPaymentProvider implements PaymentProviderInterface { } // Future
```

**Factory**:
```php
class PaymentProviderFactory {
    public static function forAccount(HLAccount $account, string $provider = 'paytr') {
        return match($provider) {
            'paytr' => new PayTRPaymentProvider($account),
            'iyzico' => new IyzicoPaymentProvider($account), // Future
            default => throw new \Exception("Unsupported provider"),
        };
    }
}
```

---

### 3. Repository Pattern
**Purpose**: Data access abstraction

**Implementation**:
```php
class PaymentRepository {
    public function findByTransactionId(string $transactionId): ?Payment {
        return Payment::where('transaction_id', $transactionId)->first();
    }

    public function getRecentPayments(string $locationId, int $limit = 50) {
        return Payment::where('location_id', $locationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
```

**Files**:
- `app/Repositories/PaymentRepository.php`
- `app/Repositories/HighLevelAccountRepository.php`
- `app/Repositories/WebhookLogRepository.php`

---

### 4. Factory Pattern
**Purpose**: Create complex objects (test data)

**Implementation**:
```php
class HLAccountFactory extends Factory {
    public function definition() {
        return [
            'location_id' => 'loc_' . $this->faker->uuid,
            'access_token' => 'token_' . Str::random(40),
            'refresh_token' => 'refresh_' . Str->random(40),
            // ...
        ];
    }

    public function withPayTR() {
        return $this->state([
            'paytr_merchant_id' => '123456',
            'paytr_merchant_key' => encrypt('test_key'),
            'paytr_merchant_salt' => encrypt('test_salt'),
            'paytr_configured' => true,
        ]);
    }
}
```

---

## Directory Structure

```
app/
├── Http/Controllers/
│   ├── Controller.php              # Base controller
│   ├── PaymentController.php       # Payment operations
│   ├── OAuthController.php         # HighLevel OAuth
│   ├── WebhookController.php       # Webhook handling
│   ├── PayTRSetupController.php    # PayTR configuration
│   ├── ConfigController.php        # App configuration
│   └── LandingPageController.php   # Public landing page
│
├── Services/
│   ├── PaymentService.php          # Payment business logic
│   ├── HighLevelService.php        # HighLevel API client
│   └── PayTRHashService.php        # Signature generation
│
├── PaymentGateways/
│   ├── PaymentProviderInterface.php  # Provider contract
│   ├── PayTRPaymentProvider.php      # PayTR implementation
│   └── PaymentProviderFactory.php    # Provider factory
│
├── Repositories/
│   ├── PaymentRepository.php
│   ├── HighLevelAccountRepository.php
│   └── WebhookLogRepository.php
│
├── Models/
│   ├── HLAccount.php               # HighLevel accounts
│   ├── Payment.php                 # Transactions
│   ├── PaymentMethod.php           # Saved cards
│   ├── WebhookLog.php              # Webhook logs
│   ├── UserActivityLog.php         # Audit trail
│   └── PaymentFailure.php          # Error tracking
│
├── Logging/
│   ├── PaymentLogger.php           # Payment events
│   ├── WebhookLogger.php           # Webhook events
│   └── UserActionLogger.php        # User actions
│
└── Providers/
    └── AppServiceProvider.php      # Service container bindings
```

---

## Request Flow

### Payment Initialization

```
HighLevel
    ↓ GET /payments/page
PaymentController::paymentPage()
    ↓ validate request
PaymentService::createPayment()
    ↓ create Payment record
PaymentProviderFactory::forAccount()
    ↓ get provider
PayTRPaymentProvider::initializePayment()
    ↓ generate hash
PayTR API
    ← iframe token
PaymentController
    ↓ render view
Browser (iframe with PayTR)
```

### Payment Callback

```
PayTR
    ↓ POST /payments/callback
WebhookController::paytrCallback()
    ↓ validate hash
PaymentService::processCallback()
    ↓ update Payment
    ↓ store card (if utoken)
    ↓ send webhook
HighLevelService::sendPaymentCaptured()
    ↓ POST to HighLevel
HighLevel receives event
```

---

## Dependency Injection

**Service Container Bindings**:
```php
// AppServiceProvider::register()
$this->app->singleton(PaymentService::class, function ($app) {
    return new PaymentService(
        $app->make(HighLevelService::class),
        $app->make(PaymentLogger::class)
    );
});
```

**Controller Injection**:
```php
class PaymentController extends Controller {
    public function __construct(
        protected PaymentService $paymentService,
        protected HighLevelService $highLevelService,
        protected PaymentLogger $paymentLogger,
        protected UserActionLogger $userActionLogger
    ) {}
}
```

---

## Middleware Stack

**Global Middleware**:
- `TrustProxies` - Proxy trust
- `ValidatePostSize` - Request size limit
- `TrimStrings` - Trim input
- `ConvertEmptyStringsToNull` - Normalize empty values

**Web Middleware**:
- `EncryptCookies`
- `AddQueuedCookiesToResponse`
- `StartSession`
- `VerifyCsrfToken` - CSRF protection

**API Middleware**:
- No CSRF protection (stateless)
- JSON responses

---

## Authentication Strategy

**Multi-Tenant by Location**:
```php
protected function getAccountFromRequest(Request $request): ?HLAccount
{
    $locationId = $request->header('X-Location-Id')
                  ?: $request->get('locationId');

    return HLAccount::where('location_id', $locationId)->first();
}
```

**No traditional user auth** - tenant identified by `location_id`

---

## Error Handling

**Controller Level**:
```php
try {
    $result = $this->paymentService->createPayment($account, $data);
} catch (\Exception $e) {
    Log::error('Payment creation failed', [
        'error' => $e->getMessage(),
        'account_id' => $account->id,
    ]);
    return response()->json(['error' => 'Internal error'], 500);
}
```

**Service Level**:
```php
public function createPayment(HLAccount $account, array $data): array
{
    if (!$account->hasPayTRCredentials()) {
        return [
            'success' => false,
            'error' => 'PayTR not configured',
            'redirect_to_setup' => true,
        ];
    }
    // ...
}
```

---

## Logging Architecture

**Channels** (`config/logging.php`):
- `daily` - General application logs
- `payment` - Payment-specific logs (custom channel)
- `webhook` - Webhook logs

**Usage**:
```php
Log::channel('payment')->info('Payment initialized', [
    'merchant_oid' => $merchantOid,
    'amount' => $amount,
]);
```

**Database Logging**:
- `webhook_logs` - All webhooks
- `user_activity_logs` - User actions
- `payment_failures` - Failed payments

---

## Queue System

**Status**: Not implemented yet

**Planned**:
```php
// Webhook retry queue
dispatch(new SendHighLevelWebhook($payment));

// Email notifications
dispatch(new SendPaymentReceiptEmail($payment));
```

**Configuration**: Will use Redis/Database queue driver

---

## Caching Strategy

**Status**: Minimal caching

**Implemented**:
- Laravel config cache (`php artisan config:cache`)
- Route cache (`php artisan route:cache`)
- View cache (`php artisan view:cache`)

**Future**:
- Payment method caching (per contact)
- Account credentials caching (short TTL)

---

## Database Transactions

**Payment Operations**:
```php
DB::transaction(function () use ($payment, $callbackData) {
    $payment->markAsSuccess($callbackData['payment_id']);

    if (isset($callbackData['utoken'])) {
        $this->storePaymentMethod($payment, $callbackData);
    }

    $this->highLevelService->sendPaymentCaptured($payment);
});
```

**Ensures**:
- Atomicity - All or nothing
- Consistency - Valid state always
- Isolation - No interference
- Durability - Persisted on success

---

## Testing Architecture

**Test Types**:
- Feature tests - HTTP endpoints
- Unit tests - Service logic (future)
- Integration tests - Provider APIs (future)

**Structure**:
```
tests/
├── Feature/
│   ├── OAuthControllerTest.php
│   ├── PayTRSetupControllerTest.php
│   ├── PaymentControllerTest.php
│   └── WebhookControllerTest.php
└── Unit/
    └── (future)
```

**Traits Used**:
- `DatabaseTransactions` - Rollback after each test
- `RefreshDatabase` - Fresh DB per test class (not used, prefer transactions)

---

## Environment Configuration

**Files**:
- `.env` - Local environment
- `.env.example` - Template
- `config/` - Configuration files

**Key Configs**:
- `config/services.php` - PayTR, HighLevel, external APIs
- `config/database.php` - PostgreSQL connection
- `config/logging.php` - Log channels

---

## Security Layers

**1. Encryption**:
- PayTR credentials encrypted at rest
- Laravel `encrypt()` / `decrypt()`

**2. Hash Verification**:
- All PayTR callbacks verified
- HMAC-SHA256 signatures

**3. CSRF Protection**:
- Web routes protected
- API routes exempt (stateless)

**4. SQL Injection**:
- Eloquent ORM (parameterized queries)
- Never raw SQL with user input

**5. XSS Protection**:
- Blade escaping by default (`{{ }}`)
- JSON responses sanitized

**6. Data Isolation**:
- Multi-tenant by `location_id`
- All queries scoped to tenant

---

## Performance Considerations

**Database Indexes**:
- `location_id` - Most queries filtered by this
- `status` - Payment status lookups
- `transaction_id` - External reference
- `created_at` - Chronological queries

**Query Optimization**:
```php
// Eager loading to avoid N+1
$payments = Payment::with('hlAccount')->get();

// Selective columns
$payments = Payment::select(['id', 'amount', 'status'])->get();
```

**API Response Caching** (future):
```php
return Cache::remember("payment_methods:{$contactId}", 300, function() {
    return PaymentMethod::where('contact_id', $contactId)->get();
});
```

---

## Deployment Architecture

**Current**: Development (local + ngrok)

**Production** (planned):
```
┌─────────────┐
│   Cloudflare│  (DNS, CDN, DDoS protection)
└──────┬──────┘
       │
┌──────▼──────┐
│   Laravel   │  (Laravel Forge / Vapor)
│   Server    │  (PHP 8.3, Nginx)
└──────┬──────┘
       │
┌──────▼──────┐
│  Supabase   │  (PostgreSQL)
│  Database   │
└─────────────┘
```

**Features**:
- HTTPS required
- Auto-scaling (Vapor)
- Queue workers
- Scheduled tasks (cron)
- Log aggregation (Logtail)
- Error tracking (Sentry)

---

## Code Standards

**PSR-12**: PHP coding style
**Laravel Best Practices**:
- Controllers thin, services fat
- Repository pattern for queries
- Form requests for validation
- Resources for API responses (future)

**Type Hinting**:
```php
public function createPayment(HLAccount $account, array $data): array
{
    // ...
}
```

---

## Future Enhancements

**Planned**:
1. **Iyzico Provider** - Alternative payment gateway
2. **Subscription Support** - Recurring payments
3. **Webhook Retry** - Failed webhook queue
4. **Admin Dashboard** - Analytics and monitoring
5. **Rate Limiting** - API throttling
6. **CORS Configuration** - Explicit HighLevel domains
7. **API Versioning** - `/api/v1/`
8. **GraphQL API** - Alternative to REST

---

## Related Documentation

- [database-schema.md](database-schema.md) - Database structure
- [api-endpoints.md](api-endpoints.md) - API reference
- [paytr-integration.md](paytr-integration.md) - PayTR details
- [highlevel-integration.md](highlevel-integration.md) - HighLevel details
