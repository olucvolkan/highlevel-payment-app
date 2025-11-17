# ADIM ADIM ENTEGRASYON TEST REHBERİ

> **Amaç:** HighLevel-PayTR entegrasyonunu sıfırdan test etmek için adım adım rehber
> **Durum:** Her adım bir öncekine bağlı - sırayla ilerleyin

---

## HAZIRLIK

### Terminal Setup

```bash
# Terminal 1 - Laravel Server
cd /Users/volkanoluc/Projects/highlevel-paytr-integration
php artisan serve --port=8000

# Terminal 2 - ngrok (Public URL)
ngrok http 8000

# Terminal 3 - Logs (opsiyonel)
tail -f storage/logs/laravel.log
```

### ngrok URL'inizi Kaydedin

```bash
# ngrok output'tan kopyalayın
# Örnek: https://abc123.ngrok.io

# .env dosyasını güncelleyin
APP_URL=https://abc123.ngrok.io

# Cache temizleyin
php artisan config:clear
php artisan cache:clear
```

---

## ADIM 1: HL ACCOUNT OLUŞTURMA

### 1.1 Laravel Tinker ile Account Oluştur

```bash
php artisan tinker
```

```php
// Test account oluştur
$account = \App\Models\HLAccount::create([
    'location_id' => 'test_loc_12345',
    'company_id' => 'test_company_67890',
    'user_id' => 'test_user_11111',
    'access_token' => 'test_access_token_' . \Illuminate\Support\Str::random(60),
    'refresh_token' => 'test_refresh_token_' . \Illuminate\Support\Str::random(60),
    'token_expires_at' => now()->addDays(30),
    'scopes' => [
        'payments/orders.readonly',
        'payments/orders.write',
        'payments/subscriptions.readonly',
        'payments/transactions.readonly',
        'payments/custom-provider.readonly',
        'payments/custom-provider.write',
        'products.readonly',
        'products/prices.readonly'
    ],
]);

echo "✅ Account created!\n";
echo "ID: " . $account->id . "\n";
echo "Location ID: " . $account->location_id . "\n";
exit;
```

### 1.2 Database'de Kontrol Et

```bash
# PostgreSQL (Supabase)
psql -h aws-0-eu-central-1.pooler.supabase.com \
     -p 6543 \
     -U postgres.wjphgaepbggsmhtbvwlj \
     -d postgres

# veya doğrudan query
psql -h ... -d postgres -c "
SELECT
  id,
  location_id,
  company_id,
  paytr_configured,
  created_at
FROM hl_accounts
WHERE location_id = 'test_loc_12345';
"
```

**Beklenen Output:**
```
 id |   location_id    |    company_id      | paytr_configured |     created_at
----+------------------+--------------------+------------------+---------------------
  1 | test_loc_12345   | test_company_67890 | f                | 2025-10-29 17:00:00
```

**✅ ADIM 1 TAMAMLANDI**
- Account oluşturuldu
- Database'de kayıt var
- PayTR configured: false (henüz setup yapılmadı)

---

## ADIM 2: PAYTR SETUP SAYFASINI TEST ET

### 2.1 Browser'da Setup Sayfasını Aç

**URL:**
```
https://YOUR_NGROK_URL.ngrok.io/paytr/setup?location_id=test_loc_12345
```

**Değiştirin:**
```
https://abc123.ngrok.io/paytr/setup?location_id=test_loc_12345
```

### 2.2 Görsel Kontrol

**Sayfada Görmeli:**
- ✅ "PayTR Configuration" başlığı
- ✅ Location ID: `test_loc_12345`
- ✅ Status Badge: "Not Configured" (sarı)
- ✅ Credentials formu:
  - Merchant ID input
  - Merchant Key input (password)
  - Merchant Salt input (password)
  - Test Mode checkbox
  - "Test Credentials" butonu
  - "Save Configuration" butonu

**❌ Eğer 404 Hatası Alırsanız:**
```bash
# Route cache temizle
php artisan route:clear
php artisan config:clear

# Browser'ı yenileyin
```

**❌ Eğer "Account not found" Alırsanız:**
```bash
# location_id'yi kontrol edin
# Database'de account var mı?
psql ... -c "SELECT * FROM hl_accounts WHERE location_id='test_loc_12345';"
```

**✅ ADIM 2 TAMAMLANDI**
- Setup sayfası açıldı
- Form görünüyor
- UI düzgün çalışıyor

---

## ADIM 3: PAYTR CREDENTIALS TEST ET

### 3.1 Form'u Doldur

**Test Credentials (Local Testing):**
```
Merchant ID:    test_merchant_123
Merchant Key:   test_key_abcdef123456
Merchant Salt:  test_salt_xyz789
Test Mode:      ✓ (checked)
```

### 3.2 "Test Credentials" Butonuna Tıkla

**Beklenen Davranış:**
1. Buton disable olur
2. Loading spinner görünür: "Testing..."
3. 2-3 saniye sonra sonuç gelir

**Başarılı Test Response:**
```
✅ Credentials are valid
Test mode is enabled
```

**Başarısız Test Response:**
```
❌ Test failed
Invalid credentials or connection error
```

### 3.3 Network Tab'de İstek Kontrolü

**DevTools → Network Tab → XHR:**

**Request:**
```
POST https://abc123.ngrok.io/paytr/test

Headers:
  Content-Type: application/json
  X-CSRF-TOKEN: xxx

Body:
{
  "merchant_id": "test_merchant_123",
  "merchant_key": "test_key_abcdef123456",
  "merchant_salt": "test_salt_xyz789",
  "test_mode": true,
  "location_id": "test_loc_12345"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Credentials are valid",
  "test_mode": true
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Invalid credentials",
  "details": "Hash mismatch or connection error"
}
```

### 3.4 Laravel Log'ları Kontrol Et

```bash
# Terminal 3
tail -f storage/logs/laravel.log
```

**Görmeli:**
```
[2025-10-29 17:05:00] local.INFO: PayTR credentials test requested
[2025-10-29 17:05:01] local.INFO: Test response: {"success":true,"message":"Credentials are valid"}
```

**✅ ADIM 3 TAMAMLANDI**
- Test credentials doğrulandı
- Network request başarılı
- Log kayıtları tamam

---

## ADIM 4: PAYTR CREDENTIALS KAYDET

### 4.1 "Save Configuration" Butonuna Tıkla

**Beklenen Davranış:**
1. Buton disable olur
2. Loading spinner: "Saving..."
3. Alert: "✅ Configuration saved successfully!"
4. Sayfa yenilenir

### 4.2 Sayfa Yenilendikten Sonra

**Görmeli:**
- ✅ Status Badge: "Configured" (yeşil)
- ✅ "Current Configuration" bölümü görünür:
  ```
  Merchant ID:     test_merchant_123
  Test Mode:       Enabled (sarı badge)
  Configured At:   29 Ekim 2025, 17:05
  Status:          Active (yeşil badge)
  ```
- ✅ "Remove Configuration" butonu

### 4.3 Database'de Kontrol Et

```bash
psql ... -c "
SELECT
  location_id,
  paytr_merchant_id,
  paytr_configured,
  paytr_test_mode,
  updated_at
FROM hl_accounts
WHERE location_id = 'test_loc_12345';
"
```

**Beklenen Output:**
```
   location_id    | paytr_merchant_id | paytr_configured | paytr_test_mode |     updated_at
------------------+-------------------+------------------+-----------------+---------------------
 test_loc_12345   | test_merchant_123 | t                | t               | 2025-10-29 17:05:00
```

**✅ Kontroller:**
- ✅ `paytr_configured = true`
- ✅ `paytr_merchant_id = test_merchant_123`
- ✅ `paytr_test_mode = true`
- ✅ `paytr_merchant_key` ve `paytr_merchant_salt` şifreli (decrypt edilmeli)

### 4.4 Şifreli Credentials Kontrolü

```bash
php artisan tinker
```

```php
$account = \App\Models\HLAccount::where('location_id', 'test_loc_12345')->first();

echo "Merchant ID: " . $account->paytr_merchant_id . "\n";
echo "Test Mode: " . ($account->paytr_test_mode ? 'Yes' : 'No') . "\n";

// Decrypt credentials
$credentials = $account->getPayTRCredentials();
echo "\nDecrypted Credentials:\n";
echo "Key: " . $credentials['merchant_key'] . "\n";
echo "Salt: " . $credentials['merchant_salt'] . "\n";

exit;
```

**Beklenen Output:**
```
Merchant ID: test_merchant_123
Test Mode: Yes

Decrypted Credentials:
Key: test_key_abcdef123456
Salt: test_salt_xyz789
```

**✅ ADIM 4 TAMAMLANDI**
- Credentials kaydedildi
- Database'de şifreli olarak saklandı
- Decrypt edilebildi

---

## ADIM 5: PAYMENT PAGE TEST ET

### 5.1 Payment Page URL'ini Oluştur

**URL Template:**
```
https://YOUR_NGROK_URL.ngrok.io/payments/page?locationId=LOCATION_ID&transactionId=TXN_ID&amount=AMOUNT&currency=CURRENCY&email=EMAIL&contactId=CONTACT_ID
```

**Örnek:**
```
https://abc123.ngrok.io/payments/page?locationId=test_loc_12345&transactionId=txn_test_001&amount=10000&currency=TRY&email=test@example.com&contactId=cont_test_999
```

**Parametreler:**
- `locationId`: test_loc_12345 (account'umuz)
- `transactionId`: txn_test_001 (unique ID)
- `amount`: 10000 (100.00 TRY - kuruş cinsinden)
- `currency`: TRY
- `email`: test@example.com
- `contactId`: cont_test_999

### 5.2 Browser'da Payment Page'i Aç

**Beklenen Görünüm:**

**1. Loading State (2-3 saniye):**
```
┌─────────────────────────────────┐
│                                 │
│        [Loading spinner]        │
│                                 │
│   Initializing payment...       │
│                                 │
└─────────────────────────────────┘
```

**2. PayTR iframe Yüklendi:**
```
┌─────────────────────────────────┐
│  PayTR Güvenli Ödeme            │
│                                 │
│  Kart Numarası: [____________]  │
│  CVC:           [___]           │
│  Expiry:        [__/__]         │
│                                 │
│  [Ödeme Yap]                    │
└─────────────────────────────────┘
```

### 5.3 Browser Console Kontrolü

**DevTools → Console:**

**Görmeli:**
```javascript
// 1. Config loaded
{
  merchantOid: "ORDER_1698765432_1234",
  transactionId: "txn_test_001",
  amount: 10000,
  currency: "TRY",
  iframeUrl: "https://www.paytr.com/odeme/guvenli/abc123..."
}

// 2. iframe loaded
iframe onload triggered

// 3. postMessage sent
Sending postMessage: custom_provider_ready
{
  type: "custom_provider_ready",
  data: {
    merchantOid: "ORDER_1698765432_1234",
    transactionId: "txn_test_001"
  }
}

// 4. Polling started
Starting payment status polling...
```

### 5.4 Database'de Payment Record Kontrolü

```bash
psql ... -c "
SELECT
  id,
  merchant_oid,
  transaction_id,
  amount,
  currency,
  status,
  created_at
FROM payments
WHERE transaction_id = 'txn_test_001';
"
```

**Beklenen Output:**
```
 id |     merchant_oid      | transaction_id | amount | currency | status  |     created_at
----+-----------------------+----------------+--------+----------+---------+---------------------
  1 | ORDER_1698765432_1234 | txn_test_001   |  100.00| TRY      | pending | 2025-10-29 17:10:00
```

**✅ ADIM 5 TAMAMLANDI**
- Payment page açıldı
- PayTR iframe yüklendi
- postMessage gönderildi
- Database'de payment record oluşturuldu
- Status: pending

---

## ADIM 6: PAYMENT CALLBACK SİMÜLASYONU

### 6.1 PayTR Callback Hash Hesapla

```bash
php artisan tinker
```

```php
$account = \App\Models\HLAccount::where('location_id', 'test_loc_12345')->first();
$credentials = $account->getPayTRCredentials();

$merchantOid = 'ORDER_1698765432_1234'; // Payment'tan al
$status = 'success';
$totalAmount = '10000'; // Kuruş cinsinden

$hashStr = $merchantOid . $credentials['merchant_salt'] . $status . $totalAmount;
$hash = base64_encode(hash_hmac('sha256', $hashStr, $credentials['merchant_key'], true));

echo "Callback Hash: " . $hash . "\n";
exit;
```

**Örnek Output:**
```
Callback Hash: abc123def456ghi789jkl012mno345pqr678stu901vwx234yz=
```

### 6.2 cURL ile Callback Gönder

```bash
curl -X POST https://abc123.ngrok.io/api/callbacks/paytr \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_oid": "ORDER_1698765432_1234",
    "status": "success",
    "total_amount": "10000",
    "payment_id": "paytr_payment_123456",
    "hash": "abc123def456ghi789jkl012mno345pqr678stu901vwx234yz=",
    "payment_type": "card",
    "installment_count": "0",
    "currency": "TRY",
    "test_mode": "1"
  }'
```

**Beklenen Response:**
```
OK
```

**❌ Eğer "PAYTR notification failed: invalid hash" Alırsanız:**
```bash
# Hash hesaplama doğru mu kontrol et
# merchant_salt ve merchant_key decrypt edilebiliyor mu?

php artisan tinker
$account = \App\Models\HLAccount::where('location_id', 'test_loc_12345')->first();
$creds = $account->getPayTRCredentials();
var_dump($creds);
exit;
```

### 6.3 Database'de Payment Status Kontrolü

```bash
psql ... -c "
SELECT
  merchant_oid,
  status,
  charge_id,
  provider_payment_id,
  paid_at
FROM payments
WHERE merchant_oid = 'ORDER_1698765432_1234';
"
```

**Beklenen Output:**
```
     merchant_oid      | status  |    charge_id     | provider_payment_id |       paid_at
-----------------------+---------+------------------+---------------------+---------------------
 ORDER_1698765432_1234 | success | chrg_xxx         | paytr_payment_123456| 2025-10-29 17:15:00
```

**✅ Kontroller:**
- ✅ `status = success` (pending'den success'e değişti)
- ✅ `charge_id` dolu
- ✅ `provider_payment_id = paytr_payment_123456`
- ✅ `paid_at` timestamp var

### 6.4 Laravel Log Kontrolü

```bash
tail -20 storage/logs/laravel.log
```

**Görmeli:**
```
[2025-10-29 17:15:00] local.INFO: PayTR callback received
[2025-10-29 17:15:00] local.INFO: Payment marked as success: ORDER_1698765432_1234
[2025-10-29 17:15:00] local.INFO: HighLevel webhook sent: payment.captured
```

**✅ ADIM 6 TAMAMLANDI**
- Callback başarılı
- Payment status güncellendi
- Log kayıtları tamam

---

## ADIM 7: PAYMENT QUERY ENDPOINT TEST ET

### 7.1 Verify Payment

```bash
curl -X POST https://abc123.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "verify",
    "locationId": "test_loc_12345",
    "transactionId": "txn_test_001",
    "chargeId": "chrg_xxx"
  }'
```

**Beklenen Response:**
```json
{
  "success": true,
  "failed": false,
  "chargeId": "chrg_xxx",
  "transactionId": "txn_test_001",
  "amount": 10000,
  "currency": "TRY",
  "status": "success",
  "paidAt": "2025-10-29T17:15:00.000000Z"
}
```

### 7.2 List Payment Methods (Henüz Kayıtlı Kart Yok)

```bash
curl -X POST https://abc123.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "list_payment_methods",
    "locationId": "test_loc_12345",
    "contactId": "cont_test_999"
  }'
```

**Beklenen Response:**
```json
{
  "methods": []
}
```

### 7.3 Refund Test (Opsiyonel)

```bash
# Önce payment ID'yi al
psql ... -c "SELECT id FROM payments WHERE merchant_oid = 'ORDER_1698765432_1234';"
# Örnek: id = 1

curl -X POST https://abc123.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "refund",
    "locationId": "test_loc_12345",
    "chargeId": "chrg_xxx",
    "amount": 5000
  }'
```

**Beklenen Response (Success):**
```json
{
  "success": true,
  "message": "Refund processed successfully"
}
```

**Not:** Test mode'da gerçek refund işlemi yapılmaz, sadece simüle edilir.

**✅ ADIM 7 TAMAMLANDI**
- Query endpoint çalışıyor
- Verify success
- List methods çalışıyor

---

## ADIM 8: PAYMENT STATUS POLLING TEST

### 8.1 Polling Endpoint Test

```bash
curl -X POST https://abc123.ngrok.io/api/payments/status \
  -H "Content-Type: application/json" \
  -d '{
    "merchantOid": "ORDER_1698765432_1234",
    "transactionId": "txn_test_001"
  }'
```

**Beklenen Response (Success):**
```json
{
  "status": "success",
  "chargeId": "chrg_xxx",
  "amount": 10000,
  "currency": "TRY"
}
```

**Beklenen Response (Pending):**
```json
{
  "status": "pending"
}
```

**Beklenen Response (Failed):**
```json
{
  "status": "failed",
  "error": "Payment declined"
}
```

**✅ ADIM 8 TAMAMLANDI**
- Polling endpoint çalışıyor
- Status dönüyor

---

## ADIM 9: HIGHLEVEL SIMULATOR İLE TAM FLOW TEST

### 9.1 Simulator HTML Dosyası Oluştur

`highlevel-simulator.html` dosyasını [LOCAL_TESTING_GUIDE.md](LOCAL_TESTING_GUIDE.md) dosyasından kopyalayın.

**Önemli: ngrok URL'inizi güncelleyin:**
```javascript
const NGROK_URL = 'https://abc123.ngrok.io'; // Kendi URL'iniz
```

### 9.2 Browser'da Açın

```bash
open highlevel-simulator.html
# veya
firefox highlevel-simulator.html
```

### 9.3 Beklenen Görünüm

```
┌────────────────────────────────────────────────┐
│ ⚡ HighLevel      Dashboard Contacts Settings  │ <- Navbar
├──────────┬───────────────────────────────────────┤
│ General  │ PayTR Payment Gateway                │
│ Profile  │ Configure merchant credentials       │
│ Phone    │                                      │
│►Integr.  │ ┌──────────────────────────────────┐ │
│ Payment  │ │ PayTR Configuration              │ │
│          │ │                                  │ │
│          │ │ Location ID: test_loc_12345      │ │
│          │ │ Status: ✅ Configured             │ │
│          │ │                                  │ │
│          │ │ [Current Configuration Display] │ │
│          │ │                                  │ │
│          │ │ Merchant ID: test_merchant_123   │ │
│          │ │ Test Mode: Enabled               │ │
│          │ │                                  │ │
│          │ └──────────────────────────────────┘ │
└──────────┴───────────────────────────────────────┘
                                  ┌──────────────────┐
                                  │ 📡 postMessage   │
                                  │ Events           │
                                  │                  │
                                  │ [No events yet]  │
                                  └──────────────────┘
```

### 9.4 Debug Panel'de Görecekleriniz

```
📡 postMessage Events

17:20:00
🚀 Loading iframe from: https://abc123.ngrok.io/paytr/setup?location_id=test_loc_12345

17:20:02
ℹ️ iframe loaded

[Daha fazla event görüntülenecek]
```

**✅ ADIM 9 TAMAMLANDI**
- HighLevel simülatörü çalışıyor
- iframe içinde setup page görünüyor
- Debug panel aktif

---

## ADIM 10: END-TO-END TEST

### 10.1 Yeni Payment Flow Başlat

**Simulator içindeki iframe'i payment page'e değiştirin:**

JavaScript Console'da:
```javascript
const paymentUrl = 'https://abc123.ngrok.io/payments/page?' +
  'locationId=test_loc_12345&' +
  'transactionId=txn_test_002&' +
  'amount=15000&' +
  'currency=TRY&' +
  'email=test2@example.com&' +
  'contactId=cont_test_888';

document.getElementById('appFrame').src = paymentUrl;
```

### 10.2 Beklenen postMessage Events

**Debug Panel'de sırayla:**

```
17:25:00
ℹ️ custom_provider_ready
{
  "type": "custom_provider_ready",
  "data": {
    "merchantOid": "ORDER_1698765500_5678",
    "transactionId": "txn_test_002"
  }
}

17:25:05
ℹ️ Polling started...

17:25:10
ℹ️ Polling status check...

[Her 5 saniyede bir polling]
```

### 10.3 Callback Gönder (Başarılı Ödeme Simüle Et)

```bash
# Yeni payment için hash hesapla
php artisan tinker
```

```php
$account = \App\Models\HLAccount::where('location_id', 'test_loc_12345')->first();
$credentials = $account->getPayTRCredentials();

$merchantOid = 'ORDER_1698765500_5678'; // Yeni merchant_oid
$status = 'success';
$totalAmount = '15000';

$hashStr = $merchantOid . $credentials['merchant_salt'] . $status . $totalAmount;
$hash = base64_encode(hash_hmac('sha256', $hashStr, $credentials['merchant_key'], true));

echo "Hash: " . $hash . "\n";
exit;
```

```bash
# Callback gönder
curl -X POST https://abc123.ngrok.io/api/callbacks/paytr \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_oid": "ORDER_1698765500_5678",
    "status": "success",
    "total_amount": "15000",
    "payment_id": "paytr_payment_789012",
    "hash": "CALCULATED_HASH_HERE",
    "payment_type": "card",
    "installment_count": "0",
    "currency": "TRY",
    "test_mode": "1"
  }'
```

### 10.4 Debug Panel'de Success Event

**Görmeli:**
```
17:25:15
✅ custom_element_success_response
{
  "type": "custom_element_success_response",
  "data": {
    "chargeId": "chrg_yyy",
    "transactionId": "txn_test_002",
    "amount": 15000,
    "currency": "TRY"
  }
}
```

**Alert Popup:**
```
Payment successful! Charge ID: chrg_yyy
```

**✅ ADIM 10 TAMAMLANDI**
- End-to-end flow çalıştı
- postMessage events başarılı
- Callback işlendi
- Success event gönderildi

---

## 📊 ÖZET CHECKLIST

### ✅ Tamamlanması Gerekenler

- [ ] **ADIM 1:** HL Account oluşturuldu
- [ ] **ADIM 2:** Setup sayfası açıldı
- [ ] **ADIM 3:** Credentials test edildi
- [ ] **ADIM 4:** Credentials kaydedildi
- [ ] **ADIM 5:** Payment page yüklendi
- [ ] **ADIM 6:** Callback işlendi
- [ ] **ADIM 7:** Query endpoint test edildi
- [ ] **ADIM 8:** Polling endpoint test edildi
- [ ] **ADIM 9:** Simulator çalıştı
- [ ] **ADIM 10:** End-to-end flow tamamlandı

---

## 🐛 SORUN GİDERME

### Problem 1: "Account not found"

**Çözüm:**
```bash
# location_id doğru mu kontrol et
psql ... -c "SELECT location_id FROM hl_accounts;"

# URL'de location_id parametresi var mı?
https://abc123.ngrok.io/paytr/setup?location_id=test_loc_12345
```

### Problem 2: "Invalid hash" Callback

**Çözüm:**
```bash
# Hash hesaplama script'ini kullan
php artisan tinker

# Credentials decrypt ediliyor mu?
$account = \App\Models\HLAccount::first();
$creds = $account->getPayTRCredentials();
var_dump($creds); // merchant_key ve merchant_salt görünmeli
```

### Problem 3: iframe Yüklenmiyor

**Çözüm:**
```bash
# HTTPS kullanıyor musunuz?
echo $APP_URL
# https:// ile başlamalı

# ngrok çalışıyor mu?
curl https://abc123.ngrok.io/
```

### Problem 4: CSRF Token Mismatch

**Çözüm:**
```bash
# Cache temizle
php artisan config:clear
php artisan cache:clear

# Browser cache temizle
# Hard reload: Cmd+Shift+R (Mac) / Ctrl+Shift+R (Win)
```

---

## 🎯 HER ADIM İÇİN cURL REQUEST'LER

### 1. Test Credentials

```bash
curl -X POST https://abc123.ngrok.io/paytr/test \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: YOUR_CSRF_TOKEN" \
  -d '{
    "merchant_id": "test_merchant_123",
    "merchant_key": "test_key_abcdef123456",
    "merchant_salt": "test_salt_xyz789",
    "test_mode": true,
    "location_id": "test_loc_12345"
  }'
```

### 2. Save Credentials

```bash
curl -X POST https://abc123.ngrok.io/paytr/credentials \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: YOUR_CSRF_TOKEN" \
  -d '{
    "merchant_id": "test_merchant_123",
    "merchant_key": "test_key_abcdef123456",
    "merchant_salt": "test_salt_xyz789",
    "test_mode": true,
    "location_id": "test_loc_12345"
  }'
```

### 3. Get Configuration

```bash
curl -X GET "https://abc123.ngrok.io/paytr/config?location_id=test_loc_12345" \
  -H "X-CSRF-TOKEN: YOUR_CSRF_TOKEN"
```

### 4. PayTR Callback

```bash
curl -X POST https://abc123.ngrok.io/api/callbacks/paytr \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_oid": "ORDER_XXX",
    "status": "success",
    "total_amount": "10000",
    "payment_id": "paytr_payment_123",
    "hash": "CALCULATED_HASH",
    "payment_type": "card",
    "installment_count": "0",
    "currency": "TRY",
    "test_mode": "1"
  }'
```

### 5. Payment Query - Verify

```bash
curl -X POST https://abc123.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "verify",
    "locationId": "test_loc_12345",
    "transactionId": "txn_test_001",
    "chargeId": "chrg_xxx"
  }'
```

### 6. Payment Query - List Methods

```bash
curl -X POST https://abc123.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "list_payment_methods",
    "locationId": "test_loc_12345",
    "contactId": "cont_test_999"
  }'
```

### 7. Payment Status Polling

```bash
curl -X POST https://abc123.ngrok.io/api/payments/status \
  -H "Content-Type: application/json" \
  -d '{
    "merchantOid": "ORDER_XXX",
    "transactionId": "txn_test_001"
  }'
```

---

## 📝 NOTLAR

### CSRF Token Alma

**Browser Console'da:**
```javascript
document.querySelector('meta[name="csrf-token"]').content
```

**veya Sayfa Kaynağında:**
```html
<meta name="csrf-token" content="abc123...">
```

### Database Bağlantı String

```bash
# Supabase
psql "postgresql://postgres.wjphgaepbggsmhtbvwlj:[YOUR-PASSWORD]@aws-0-eu-central-1.pooler.supabase.com:6543/postgres"
```

### ngrok URL Değiştiğinde

```bash
# .env güncelle
APP_URL=https://NEW_NGROK_URL.ngrok.io

# Cache temizle
php artisan config:clear
php artisan cache:clear

# Browser'ı yenile (hard reload)
```

---

*Başarılı testler! 🚀*
