# HIGHLEVEL MARKETPLACE'E DEPLOYMENT REHBERİ

> **Tarih:** 29 Ekim 2025
> **Amaç:** PayTR entegrasyonunu HighLevel Marketplace'e nasıl submit edip test edeceğimizi açıklamak

---

## İÇİNDEKİLER

1. [Önkoşullar](#önkoşullar)
2. [HighLevel Marketplace App Oluşturma](#highlevel-marketplace-app-oluşturma)
3. [Required URLs ve Konfigürasyon](#required-urls-ve-konfigürasyon)
4. [OAuth Scopes](#oauth-scopes)
5. [Test Flow](#test-flow)
6. [Production Checklist](#production-checklist)
7. [Submission Process](#submission-process)
8. [Troubleshooting](#troubleshooting)

---

## ÖNKOŞULLAR

### 1. Teknik Gereksinimler

✅ **Domain ve Hosting:**
- SSL sertifikası ile HTTPS aktif
- Public IP veya domain (localhost kullan İLAMAZ)
- Laravel app production'a deploy edilmiş

✅ **Environment Variables:**
```env
APP_URL=https://yourdomain.com
APP_ENV=production
APP_DEBUG=false

HIGHLEVEL_CLIENT_ID=your_client_id
HIGHLEVEL_CLIENT_SECRET=your_client_secret
HIGHLEVEL_REDIRECT_URI=https://yourdomain.com/oauth/callback

# PayTR Global API URL (credentials database'de)
PAYTR_API_URL=https://www.paytr.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your_database_host
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

✅ **Database Migrations:**
```bash
php artisan migrate --force
```

✅ **Composer Optimize:**
```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. HighLevel Developer Account

1. **HighLevel Marketplace'e Giriş:**
   - URL: https://marketplace.gohighlevel.com/
   - HighLevel hesabınızla login olun
   - **Developer Portal** → **My Apps**

2. **Developer Access İsteği:**
   - Eğer developer access'iniz yoksa:
   - Marketplace → "Become a Developer"
   - Form doldur ve onay bekle (genellikle 1-2 iş günü)

---

## HIGHLEVEL MARKETPLACE APP OLUŞTURMA

### 1. Yeni App Oluştur

**Marketplace Dashboard:**
1. **My Apps** → **Create New App**
2. **App Type:** Custom Payment Provider
3. **App Name:** PayTR Payment Gateway for Turkey (veya benzer)
4. **Short Name:** paytr-payment
5. **Category:** Payment Processing
6. **Subcategory:** Payment Gateway

### 2. App Bilgileri

#### Basic Information

```yaml
App Name: PayTR Payment Gateway
Description: |
  Türkiye'ye özel PayTR ödeme gateway entegrasyonu.
  HighLevel CRM içinde PayTR ile güvenli ve hızlı ödeme alın.

  Özellikler:
  - PayTR iframe entegrasyonu
  - Kredi kartı saklama
  - Test ve production mode
  - Otomatik webhook senkronizasyonu
  - Türk Lirası desteği

Short Description: Türkiye için PayTR ödeme gateway entegrasyonu

Website: https://yourdomain.com
Support Email: support@yourdomain.com
Privacy Policy URL: https://yourdomain.com/privacy
Terms of Service URL: https://yourdomain.com/terms
```

#### App Logo

- **Boyut:** 512x512 px (PNG, transparent background)
- **Format:** PNG veya JPG
- **Tasarım:** PayTR logosunu içeren basit ve profesyonel tasarım

#### Screenshots

- **Minimum 3 screenshot:**
  1. PayTR Setup sayfası
  2. Ödeme iframe'i çalışırken
  3. Başarılı ödeme sonrası

---

## REQUIRED URLS VE KONFİGÜRASYON

### 1. OAuth URLs

HighLevel Marketplace Dashboard → **App Settings** → **OAuth Configuration**

```yaml
Client ID: [HighLevel tarafından otomatik generate edilecek]
Client Secret: [HighLevel tarafından otomatik generate edilecek]

Authorization URL: https://marketplace.gohighlevel.com/oauth/chooselocation
Token URL: https://services.leadconnectorhq.com/oauth/token
Redirect URI: https://yourdomain.com/oauth/callback

# .env dosyanıza ekleyin
HIGHLEVEL_CLIENT_ID=your_client_id_here
HIGHLEVEL_CLIENT_SECRET=your_client_secret_here
```

### 2. Webhook URLs

**Marketplace Webhooks** (App install/uninstall):
```
https://yourdomain.com/api/webhooks/marketplace
```

**HTTP Method:** POST

**Events:**
- `app.install` - Yeni location'a app kurulduğunda
- `app.uninstall` - App kaldırıldığında
- `app.update` - App güncellendiğinde

### 3. Payment Provider URLs

**Query URL** (HighLevel'ın ödeme durumu sorgulaması):
```
https://yourdomain.com/api/payments/query
```

**HTTP Method:** POST

**Request Body:**
```json
{
  "type": "verify",
  "locationId": "loc_xxx",
  "transactionId": "txn_xxx",
  "chargeId": "chrg_xxx"
}
```

**Payments URL** (iframe sayfası):
```
https://yourdomain.com/payments/page
```

**HTTP Method:** GET

**Query Parameters:**
```
?locationId=loc_xxx
&transactionId=txn_xxx
&amount=10000
&currency=TRY
&email=customer@example.com
&contactId=cont_xxx
```

---

## OAUTH SCOPES

### Gerekli Scopes

HighLevel Marketplace Dashboard → **OAuth Scopes** kısmında şu scope'ları seçin:

```yaml
✅ payments/orders.readonly         # Siparişleri okuma
✅ payments/orders.write            # Sipariş oluşturma
✅ payments/subscriptions.readonly  # Abonelikleri okuma
✅ payments/transactions.readonly   # İşlemleri okuma
✅ payments/custom-provider.readonly  # Custom provider okuma
✅ payments/custom-provider.write   # Custom provider yazma
✅ products.readonly                # Ürünleri okuma
✅ products/prices.readonly         # Fiyatları okuma
```

### Scope Açıklamaları

| Scope | Neden Gerekli |
|-------|--------------|
| `payments/orders.readonly` | Ödeme siparişlerini sorgulamak için |
| `payments/orders.write` | Yeni ödeme siparişi oluşturmak için |
| `payments/subscriptions.readonly` | Abonelik bilgilerini almak için |
| `payments/transactions.readonly` | İşlem geçmişini görüntülemek için |
| `payments/custom-provider.readonly` | Provider configuration'ı okumak için |
| `payments/custom-provider.write` | Ödeme webhook'ları göndermek için |
| `products.readonly` | Ürün bilgilerini almak için |
| `products/prices.readonly` | Fiyat bilgilerini almak için |

---

## TEST FLOW

### 1. Sandbox Environment Hazırlığı

#### PayTR Test Credentials

PayTR'den test merchant credentials alın:
```
Merchant ID: test_merchant_xxx
Merchant Key: test_key_xxx
Merchant Salt: test_salt_xxx
Test Mode: true
```

Test kartları:
```
Kart Numarası: 5456165456165454
CVC: 000
Expiry: 12/26
3D Secure Code: 123456
```

#### HighLevel Test Location

1. **HighLevel hesabınızda test location oluşturun:**
   - Settings → Locations → Add Location
   - Name: "Test Location - PayTR"

2. **Marketplace'den app'i install edin:**
   - Marketplace → Your App → Install
   - Test location'ı seçin

### 2. Installation Flow Test

**Adım 1: OAuth Başlatma**
```
User clicks "Install" → HighLevel → /oauth/authorize
```

**Test:**
```bash
curl "https://marketplace.gohighlevel.com/oauth/chooselocation?\
client_id=YOUR_CLIENT_ID&\
redirect_uri=https://yourdomain.com/oauth/callback&\
response_type=code&\
scope=payments/orders.readonly payments/orders.write"
```

**Adım 2: Callback İşleme**
```
HighLevel → https://yourdomain.com/oauth/callback?code=xxx&location_id=loc_xxx
```

**Controller:** `app/Http/Controllers/OAuthController.php::callback()`

**Test:**
- Browser'da install linkine tıklayın
- Location seçin
- Authorize edin
- `/paytr/setup` sayfasına redirect edilmeli

**Adım 3: PayTR Setup**
```
User → https://yourdomain.com/paytr/setup?location_id=loc_xxx
```

**Test:**
- Merchant ID, Key, Salt girin
- "Test Credentials" butonuna tıklayın
- ✅ "Test successful" görünmeli
- "Save Configuration" ile kaydedin

### 3. Payment Flow Test

**Adım 1: Payment Iframe Oluşturma**

HighLevel'da test ödeme başlatın:
- Campaigns → Funnels → Add Payment Element
- Payment Provider: PayTR Payment Gateway
- Amount: 100 TRY

**Test URL:**
```
https://yourdomain.com/payments/page?\
locationId=loc_xxx&\
transactionId=txn_xxx&\
amount=10000&\
currency=TRY&\
email=test@example.com
```

**Beklenen:**
- PayTR iframe yüklenme li
- Loading spinner görünmeli
- `custom_provider_ready` postMessage gönderilmeli

**Adım 2: Ödeme Tamamlama**

- Test kartı bilgilerini girin
- 3D Secure ekranında "123456" girin
- Submit edin

**Beklenen:**
- PayTR callback: `POST /api/callbacks/paytr`
- Payment status: `success`
- `custom_element_success_response` postMessage gönderilmeli
- HighLevel'a webhook: `payment.captured`

**Adım 3: Verification**

```bash
# Database'de payment kontrolü
psql -d highlevel_payments -c "SELECT * FROM payments WHERE transaction_id='txn_xxx';"

# Status: success olmalı
# charge_id dolu olmalı
# paid_at timestamp olmalı
```

### 4. Webhook Test

#### PayTR → App Webhook

**Callback Hash Validation:**
```php
$hash = base64_encode(hash_hmac('sha256',
    $merchant_oid . $merchant_salt . $status . $total_amount,
    $merchant_key,
    true
));

if ($hash !== $_POST['hash']) {
    return 'PAYTR notification failed: invalid hash';
}
```

**Test:**
```bash
curl -X POST https://yourdomain.com/api/callbacks/paytr \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_oid": "ORDER_12345",
    "status": "success",
    "total_amount": "10000",
    "payment_id": "pay_xxx",
    "hash": "calculated_hash_here"
  }'
```

**Beklenen Response:**
```
OK
```

#### App → HighLevel Webhook

**Event: payment.captured**
```bash
curl -X POST https://backend.leadconnectorhq.com/payments/custom-provider/webhook \
  -H "Authorization: Bearer ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "locationId": "loc_xxx",
    "event": "payment.captured",
    "chargeId": "chrg_xxx",
    "transactionId": "txn_xxx",
    "amount": 10000,
    "chargedAt": 1698765432
  }'
```

### 5. Query Endpoint Test

**Type: verify**
```bash
curl -X POST https://yourdomain.com/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "verify",
    "locationId": "loc_xxx",
    "transactionId": "txn_xxx",
    "chargeId": "chrg_xxx"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "failed": false,
  "chargeId": "chrg_xxx",
  "transactionId": "txn_xxx",
  "amount": 10000,
  "currency": "TRY",
  "status": "success"
}
```

**Type: list_payment_methods**
```bash
curl -X POST https://yourdomain.com/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "list_payment_methods",
    "locationId": "loc_xxx",
    "contactId": "cont_xxx"
  }'
```

**Expected Response:**
```json
{
  "methods": [
    {
      "id": "pm_xxx",
      "type": "card",
      "last4": "5454",
      "brand": "mastercard",
      "expiryMonth": "12",
      "expiryYear": "2026"
    }
  ]
}
```

---

## PRODUCTION CHECKLIST

### 1. Environment Hazırlığı

#### ✅ Laravel Configuration

```bash
# .env dosyasını production'a göre ayarla
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Cache'leri temizle ve rebuild et
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Production optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

#### ✅ Database Migrations

```bash
# Production database'de migrations çalıştır
php artisan migrate --force

# Seeder'lar varsa (opsiyonel)
php artisan db:seed --class=ProductionSeeder
```

#### ✅ SSL Certificate

- HTTPS aktif olmalı
- Valid SSL certificate (Let's Encrypt veya ücretli)
- Mixed content warning olmamalı

```bash
# SSL test
curl -I https://yourdomain.com
# HTTP/2 200 görünmeli
```

### 2. PayTR Production Credentials

#### PayTR Production Merchant Panel

1. **PayTR'ye başvuru:**
   - https://www.paytr.com
   - Ticari firma bilgileri
   - Vergi levhası
   - Sözleşme imzalama

2. **Production Credentials Al:**
   ```
   Merchant ID: XXXXXX
   Merchant Key: XXXXXXXXXXXXXXXX
   Merchant Salt: XXXXXXXXXXXXXXXX
   Test Mode: false
   ```

3. **Callback URL Tanımla:**
   ```
   PayTR Merchant Panel → Entegrasyonlar → Callback URL
   URL: https://yourdomain.com/api/callbacks/paytr
   ```

### 3. Error Monitoring

#### Sentry Integration

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=your_dsn_here
```

**.env:**
```env
SENTRY_LARAVEL_DSN=https://xxx@sentry.io/xxx
SENTRY_TRACES_SAMPLE_RATE=0.2
```

**app/Exceptions/Handler.php:**
```php
public function register()
{
    $this->reportable(function (Throwable $e) {
        if (app()->bound('sentry')) {
            app('sentry')->captureException($e);
        }
    });
}
```

### 4. Logging

#### Log Rotation

**config/logging.php:**
```php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'error'),
    'days' => 14, // 14 gün log tut
],
```

#### Payment Logs

- `storage/logs/payments/` - Ödeme logları
- `webhook_logs` table - Webhook geçmişi
- `user_activity_logs` table - Kullanıcı aktiviteleri

### 5. Security

#### Rate Limiting

**routes/api.php:**
```php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/payments/query', [PaymentController::class, 'query']);
    Route::post('/callbacks/paytr', [WebhookController::class, 'paytrCallback']);
});
```

#### CORS Configuration

**config/cors.php:**
```php
'allowed_origins' => [
    'https://app.gohighlevel.com',
    'https://marketplace.gohighlevel.com',
    'https://backend.leadconnectorhq.com',
],
```

#### Firewall Rules

- Database: Sadece app server'dan erişim
- Admin panel: IP whitelist
- API endpoints: Rate limiting

### 6. Backup Strategy

```bash
# Database backup (daily cron)
0 2 * * * pg_dump highlevel_payments > /backups/db_$(date +\%Y\%m\%d).sql

# File backup
0 3 * * * tar -czf /backups/storage_$(date +\%Y\%m\%d).tar.gz /path/to/app/storage
```

---

## SUBMISSION PROCESS

### 1. App Submission

**HighLevel Marketplace Dashboard:**

1. **My Apps** → **Your App** → **Submit for Review**

2. **Submission Form:**
   - ✅ OAuth configuration tamamlandı
   - ✅ Webhook URLs test edildi
   - ✅ Payment flow baştan sona çalışıyor
   - ✅ Test location'da success ödeme yapıldı
   - ✅ Screenshots eklendi
   - ✅ Documentation hazır

3. **Testing Instructions:**
   ```
   Test Credentials:
   - Test location'da app'i install edin
   - PayTR setup page'de test credentials girin
   - Test payment flow'u çalıştırın
   - Test kartı: 5456165456165454 (CVC: 000, 3D Code: 123456)
   ```

4. **Support Information:**
   ```
   Support Email: support@yourdomain.com
   Documentation: https://yourdomain.com/docs
   Video Tutorial: https://youtube.com/xxx (opsiyonel)
   ```

### 2. Review Process

**Timeline:**
- **Initial Review:** 3-5 iş günü
- **Technical Review:** 5-7 iş günü
- **Security Audit:** 2-3 iş günü
- **Total:** 10-15 iş günü

**Review Criteria:**
- ✅ OAuth flow çalışıyor mu?
- ✅ Payment flow end-to-end test edildi mi?
- ✅ Webhook'lar doğru çalışıyor mu?
- ✅ Error handling uygun mu?
- ✅ Security best practices uygulanmış mı?
- ✅ UI/UX kullanıcı dostu mu?

### 3. Approval Sonrası

**Public Listing:**
- App marketplace'de görünür olacak
- Users install edebilecek
- Pricing plan ayarlayabilirsiniz (opsiyonel)

**Pricing Options:**
- Free
- One-time fee
- Monthly subscription
- Transaction fee (%)

**Örnek Pricing:**
```
- Free (ilk 100 işlem)
- $29/month (unlimited işlem)
- veya %1.5 transaction fee
```

---

## TROUBLESHOOTING

### Yaygın Sorunlar ve Çözümleri

#### 1. OAuth "Invalid Redirect URI" Hatası

**Problem:**
```
Error: redirect_uri_mismatch
```

**Çözüm:**
```bash
# .env kontrolü
echo $HIGHLEVEL_REDIRECT_URI
# https://yourdomain.com/oauth/callback olmalı (HTTPS!)

# HighLevel Dashboard'da URL kontrolü
# Marketplace → App Settings → OAuth → Redirect URI
# Tamamen eşleşmeli (trailing slash dahil)
```

#### 2. "Invalid Hash" PayTR Callback

**Problem:**
```
PAYTR notification failed: invalid hash
```

**Çözüm:**
```php
// Hash calculation doğru mu kontrol et
$hash_str = $merchant_oid . $merchant_salt . $status . $total_amount;
$hash = base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));

// Log ekle
Log::info('PayTR Hash Debug', [
    'received_hash' => $_POST['hash'],
    'calculated_hash' => $hash,
    'merchant_oid' => $merchant_oid,
]);
```

#### 3. "Account Not Found" Query Endpoint

**Problem:**
```json
{"error": "Invalid account", "status": 401}
```

**Çözüm:**
```bash
# Database'de account kontrolü
psql -d highlevel_payments -c "SELECT * FROM hl_accounts WHERE location_id='loc_xxx';"

# OAuth flow tekrar çalıştır
# Install → Authorize → Setup
```

#### 4. Payment iframe Yüklenmiyor

**Problem:**
- Blank screen
- CORS error
- Mixed content warning

**Çözüm:**
```php
// CORS headers kontrolü
// app/Http/Middleware/Cors.php
return $next($request)
    ->header('Access-Control-Allow-Origin', 'https://app.gohighlevel.com')
    ->header('X-Frame-Options', 'ALLOW-FROM https://app.gohighlevel.com');

// HTTPS kontrolü
if (!request()->secure() && app()->environment('production')) {
    return redirect()->secure(request()->getRequestUri());
}
```

#### 5. Webhook "401 Unauthorized" HighLevel

**Problem:**
```
Failed to send webhook to HighLevel: 401
```

**Çözüm:**
```php
// Token expiry kontrolü
if ($account->token_expires_at < now()) {
    $this->highLevelService->refreshToken($account);
}

// Webhook payload kontrolü
Log::info('Sending webhook to HighLevel', [
    'url' => $webhookUrl,
    'payload' => $payload,
    'token' => substr($account->access_token, 0, 20) . '...',
]);
```

#### 6. "SSL Certificate Verify Failed"

**Problem:**
```
cURL error 60: SSL certificate problem
```

**Çözüm:**
```bash
# SSL certificate yenile
certbot renew

# Certificate chain kontrolü
openssl s_client -connect yourdomain.com:443 -showcerts

# Laravel'da SSL verify disable (SADECE development)
# .env
CURL_VERIFY=false
```

### Debug Mode

**Development:**
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

**Production:**
```env
APP_DEBUG=false
LOG_LEVEL=error
```

**Specific Debug:**
```php
// PaymentController.php
Log::channel('payments')->debug('Payment initialization', [
    'location_id' => $locationId,
    'amount' => $amount,
    'transaction_id' => $transactionId,
]);
```

---

## İLETİŞİM VE DESTEK

### HighLevel Support

- **Marketplace Support:** marketplace-support@gohighlevel.com
- **Developer Docs:** https://highlevel.stoplight.io/
- **Community Forum:** https://community.gohighlevel.com/

### PayTR Support

- **Teknik Destek:** destek@paytr.com
- **Telefon:** +90 XXX XXX XX XX
- **Documentation:** https://www.paytr.com/dokumantasyon

---

## SONUÇ

Bu dokümantasyon, PayTR entegrasyonunuzu HighLevel Marketplace'e submit etmek ve test etmek için gereken tüm adımları içermektedir.

**Önemli Hatırlatmalar:**
1. ✅ **Test mode'da kapsamlı test yapın**
2. ✅ **Production'a geçmeden önce tüm checklist'i tamamlayın**
3. ✅ **Error monitoring (Sentry) aktif olsun**
4. ✅ **Backup stratejisi hazır olsun**
5. ✅ **Support email/documentation hazır olsun**

**Başarı Kriterleri:**
- OAuth flow sorunsuz çalışıyor ✅
- Payment end-to-end test edildi ✅
- Webhook'lar doğru çalışıyor ✅
- Error handling uygun ✅
- UI/UX kullanıcı dostu ✅

---

*Bu dokümantasyon 29 Ekim 2025 tarihinde oluşturulmuştur.*
