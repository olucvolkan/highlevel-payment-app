# Database Schema

> **Database**: PostgreSQL 15 (Supabase)
> **ORM**: Laravel Eloquent
> **Migrations**: Located in `database/migrations/`

## Table Overview

| Table | Purpose | Records |
|-------|---------|---------|
| `hl_accounts` | HighLevel location credentials & PayTR config | Multi-tenant accounts |
| `payments` | Payment transactions | All payment records |
| `payment_methods` | Stored cards (tokenized) | Customer saved cards |
| `webhook_logs` | Webhook request/response logs | All webhooks |
| `user_activity_logs` | User action tracking | Audit trail |
| `payment_failures` | Failed payment records | Error analysis |

---

## Core Tables

### `hl_accounts`
**Purpose**: Store HighLevel OAuth credentials and PayTR configuration per location

```sql
CREATE TABLE hl_accounts (
    id BIGSERIAL PRIMARY KEY,

    -- HighLevel OAuth
    location_id VARCHAR UNIQUE NOT NULL,
    company_id VARCHAR,
    user_id VARCHAR,
    access_token TEXT NOT NULL,
    refresh_token TEXT,
    token_expires_at TIMESTAMP,
    integration_id VARCHAR,
    config_id VARCHAR,
    is_active BOOLEAN DEFAULT true,
    scopes JSONB,

    -- PayTR Credentials (Added via migration 2025_10_25)
    paytr_merchant_id VARCHAR,
    paytr_merchant_key TEXT,           -- Encrypted
    paytr_merchant_salt VARCHAR,        -- Encrypted
    paytr_test_mode BOOLEAN DEFAULT true,
    paytr_configured BOOLEAN DEFAULT false,
    paytr_configured_at TIMESTAMP,

    -- Metadata
    metadata JSONB,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE UNIQUE INDEX hl_accounts_location_id_index ON hl_accounts(location_id);
CREATE INDEX hl_accounts_company_id_index ON hl_accounts(company_id);
```

**Key Points**:
- `location_id` is the primary tenant identifier from HighLevel
- PayTR credentials are encrypted at model level via Laravel `encrypt()`
- Supports soft deletes (`deleted_at`)

**Model**: `app/Models/HLAccount.php`

**Relations**:
- `hasMany(Payment::class)`
- `hasMany(PaymentMethod::class)`
- `hasMany(WebhookLog::class)`

---

### `payments`
**Purpose**: Transaction records for all payment operations

```sql
CREATE TABLE payments (
    id BIGSERIAL PRIMARY KEY,
    hl_account_id BIGINT NOT NULL REFERENCES hl_accounts(id) ON DELETE CASCADE,
    location_id VARCHAR NOT NULL,
    contact_id VARCHAR,

    -- Transaction Identifiers
    merchant_oid VARCHAR UNIQUE NOT NULL,    -- Our unique order ID
    transaction_id VARCHAR,                   -- HighLevel transaction ID
    charge_id VARCHAR,                        -- Returned to HighLevel
    subscription_id VARCHAR,
    order_id VARCHAR,

    -- Provider Info
    provider VARCHAR DEFAULT 'paytr',         -- paytr | iyzico
    provider_payment_id VARCHAR,              -- PayTR internal ID

    -- Payment Details
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'TRY',
    status VARCHAR DEFAULT 'pending',         -- pending | success | failed | refunded
    payment_mode VARCHAR DEFAULT 'payment',   -- payment | subscription
    payment_type VARCHAR DEFAULT 'card',
    installment_count INTEGER DEFAULT 0,

    -- User Info
    user_ip VARCHAR,
    email VARCHAR,
    user_basket TEXT,

    -- Metadata
    metadata JSONB,
    error_message TEXT,
    paid_at TIMESTAMP,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX payments_location_id_status_index ON payments(location_id, status);
CREATE INDEX payments_transaction_id_index ON payments(transaction_id);
CREATE INDEX payments_contact_id_index ON payments(contact_id);
CREATE INDEX payments_subscription_id_index ON payments(subscription_id);
CREATE INDEX payments_provider_index ON payments(provider);
CREATE INDEX payments_status_index ON payments(status);
CREATE INDEX payments_created_at_index ON payments(created_at);
```

**Status Values**:
- `pending` - Initial state
- `success` - Payment completed (via callback)
- `failed` - Payment failed
- `refunded` - Full refund processed
- `partial_refund` - Partial refund

**Model**: `app/Models/Payment.php`

**Methods**:
- `markAsSuccess($chargeId)` - Update status to success
- `markAsFailed($errorMessage)` - Update status to failed

---

### `payment_methods`
**Purpose**: Store tokenized card information for saved payments

```sql
CREATE TABLE payment_methods (
    id BIGSERIAL PRIMARY KEY,
    hl_account_id BIGINT NOT NULL REFERENCES hl_accounts(id) ON DELETE CASCADE,
    location_id VARCHAR NOT NULL,
    contact_id VARCHAR NOT NULL,

    -- Provider Tokens
    provider VARCHAR DEFAULT 'paytr',
    utoken VARCHAR NOT NULL,           -- User token (PayTR)
    ctoken VARCHAR,                    -- Card token (PayTR)

    -- Card Display Info
    card_type VARCHAR,                 -- credit | debit
    card_last_four VARCHAR(4),
    card_brand VARCHAR,                -- visa | mastercard | amex
    card_holder_name VARCHAR,
    expiry_month VARCHAR(2),
    expiry_year VARCHAR(4),

    -- Settings
    is_default BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX payment_methods_location_id_contact_id_index
    ON payment_methods(location_id, contact_id);
CREATE INDEX payment_methods_utoken_index ON payment_methods(utoken);
```

**Model**: `app/Models/PaymentMethod.php`

**Usage**: Automatically created when PayTR callback includes `utoken`

---

### `webhook_logs`
**Purpose**: Log all incoming webhooks for debugging and audit

```sql
CREATE TABLE webhook_logs (
    id BIGSERIAL PRIMARY KEY,
    hl_account_id BIGINT REFERENCES hl_accounts(id) ON DELETE SET NULL,
    location_id VARCHAR,

    -- Webhook Details
    source VARCHAR NOT NULL,           -- paytr | highlevel | marketplace
    event_type VARCHAR,                -- app.install | payment.captured
    endpoint VARCHAR,                  -- /api/webhooks/marketplace

    -- Request/Response
    payload JSONB NOT NULL,
    headers JSONB,
    response JSONB,
    status_code INTEGER,

    -- Processing
    processed BOOLEAN DEFAULT false,
    processed_at TIMESTAMP,
    error_message TEXT,

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX webhook_logs_source_index ON webhook_logs(source);
CREATE INDEX webhook_logs_event_type_index ON webhook_logs(event_type);
CREATE INDEX webhook_logs_created_at_index ON webhook_logs(created_at);
```

**Model**: `app/Models/WebhookLog.php`

**Sources**:
- `paytr` - PayTR payment callbacks
- `highlevel` - HighLevel payment webhooks
- `marketplace` - HighLevel marketplace events (install/uninstall)

---

### `user_activity_logs`
**Purpose**: Track all user and system actions for audit trail

```sql
CREATE TABLE user_activity_logs (
    id BIGSERIAL PRIMARY KEY,
    hl_account_id BIGINT REFERENCES hl_accounts(id) ON DELETE CASCADE,
    location_id VARCHAR,

    -- User Info
    user_id VARCHAR,
    user_email VARCHAR,
    user_ip VARCHAR,
    user_agent TEXT,

    -- Action Details
    action VARCHAR NOT NULL,           -- oauth_success | paytr_configured | payment_created
    description TEXT,
    entity_type VARCHAR,               -- Payment | HLAccount | PaymentMethod
    entity_id BIGINT,

    -- Metadata
    metadata JSONB,

    created_at TIMESTAMP
);

CREATE INDEX user_activity_logs_action_index ON user_activity_logs(action);
CREATE INDEX user_activity_logs_location_id_index ON user_activity_logs(location_id);
CREATE INDEX user_activity_logs_created_at_index ON user_activity_logs(created_at);
```

**Model**: `app/Models/UserActivityLog.php`

**Common Actions**:
- `oauth_success` - OAuth flow completed
- `oauth_failed` - OAuth flow failed
- `paytr_configured` - PayTR credentials saved
- `paytr_removed` - PayTR configuration deleted
- `payment_created` - New payment initiated
- `refund_processed` - Refund completed

---

### `payment_failures`
**Purpose**: Detailed error tracking for failed payments

```sql
CREATE TABLE payment_failures (
    id BIGSERIAL PRIMARY KEY,
    payment_id BIGINT REFERENCES payments(id) ON DELETE CASCADE,
    hl_account_id BIGINT REFERENCES hl_accounts(id) ON DELETE CASCADE,
    location_id VARCHAR,

    -- Transaction IDs
    merchant_oid VARCHAR,
    transaction_id VARCHAR,

    -- Provider Info
    provider VARCHAR,
    error_code VARCHAR,
    error_message TEXT,
    failure_reason TEXT,

    -- Request/Response
    request_data JSONB,
    response_data JSONB,

    -- User Info
    user_ip VARCHAR,

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX payment_failures_payment_id_index ON payment_failures(payment_id);
CREATE INDEX payment_failures_error_code_index ON payment_failures(error_code);
CREATE INDEX payment_failures_created_at_index ON payment_failures(created_at);
```

**Model**: `app/Models/PaymentFailure.php`

**Usage**: Auto-created by `PaymentService::logPaymentFailure()`

---

## Relationships

```
hl_accounts (1) ──┬── (N) payments
                  ├── (N) payment_methods
                  ├── (N) webhook_logs
                  ├── (N) user_activity_logs
                  └── (N) payment_failures

payments (1) ──── (N) payment_failures
```

## Indexes

**Performance-critical indexes**:
- `hl_accounts.location_id` (UNIQUE) - Tenant lookup
- `payments.merchant_oid` (UNIQUE) - PayTR callback lookup
- `payments(location_id, status)` - Dashboard queries
- `payment_methods(location_id, contact_id)` - Card list queries
- `webhook_logs.created_at` - Log pagination

## Security

**Encrypted Fields** (via Laravel `encrypt()`):
- `hl_accounts.paytr_merchant_key`
- `hl_accounts.paytr_merchant_salt`

**Hidden in JSON** (via Model `$hidden`):
- All encrypted fields
- `access_token`
- `refresh_token`

## Migration Files

1. `2025_10_23_215440_create_hl_accounts_table.php`
2. `2025_10_25_154316_add_paytr_credentials_to_hl_accounts_table.php`
3. `2025_10_23_215440_create_payments_table.php`
4. `2025_10_23_215441_create_payment_methods_table.php`
5. `2025_10_23_215441_create_webhook_logs_table.php`
6. `2025_10_23_215441_create_user_activity_logs_table.php`
7. `2025_10_23_215441_create_payment_failures_table.php`

## Database Seeding

**Factories**: `database/factories/`
- `HLAccountFactory.php` - Test accounts
- `PaymentFactory.php` - Test payments
- `PaymentMethodFactory.php` - Test cards

**Usage**:
```php
// Create test account with PayTR config
$account = HLAccount::factory()->create([
    'location_id' => 'test_loc_123',
    'paytr_configured' => true,
]);

// Create test payment
$payment = Payment::factory()->for($account)->create([
    'status' => 'success',
    'amount' => 100.00,
]);
```

## Common Queries

```php
// Get all successful payments for a location
$payments = Payment::where('location_id', $locationId)
    ->where('status', 'success')
    ->with('hlAccount')
    ->orderBy('created_at', 'desc')
    ->get();

// Get saved cards for a contact
$cards = PaymentMethod::where('location_id', $locationId)
    ->where('contact_id', $contactId)
    ->where('is_active', true)
    ->get();

// Get recent webhook activity
$webhooks = WebhookLog::where('source', 'paytr')
    ->orderBy('created_at', 'desc')
    ->limit(100)
    ->get();
```

## Notes

- All timestamps use PostgreSQL `TIMESTAMP` (no timezone)
- `deleted_at` enables soft deletes on most tables
- JSON columns use `JSONB` for better performance
- Foreign keys use `ON DELETE CASCADE` for automatic cleanup
