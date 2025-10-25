# PayTR API İstekleri ve Sistem Logları

Bu dokümantasyon, HighLevel-PayTR entegrasyonunda PayTR'ye atılan istekleri ve sistem loglarını detaylandırır.

## 📡 PayTR API İstekleri

### 1. Payment Token İsteği (get-token)

**Endpoint**: `POST https://www.paytr.com/odeme/api/get-token`

**Ne Zaman Atılır**: Ödeme sayfası açılırken (payment iframe oluşturulurken)

**Dosya**: `app/PaymentGateways/PayTRPaymentProvider.php:89`

```php
// İstek Parametreleri
$requestData = [
    'merchant_id' => $this->merchantId,           // Kullanıcının Merchant ID'si
    'user_ip' => $userIp,                        // Müşterinin IP adresi
    'merchant_oid' => $merchantOid,              // Benzersiz sipariş ID'si (ORDER_timestamp_random)
    'email' => $email,                           // Müşteri email
    'payment_type' => 'card',                    // Ödeme tipi
    'payment_amount' => $paymentAmount,          // Tutar (kuruş cinsinden)
    'currency' => 'TL',                          // Para birimi
    'test_mode' => $this->testMode ? '1' : '0',  // Test modu
    'non_3d' => '0',                            // 3D Secure aktif
    'merchant_ok_url' => $successUrl,            // Başarı URL'i
    'merchant_fail_url' => $failUrl,             // Hata URL'i
    'user_name' => $data['user_name'],           // Müşteri adı
    'user_address' => $data['user_address'],     // Müşteri adresi
    'user_phone' => $data['user_phone'],         // Müşteri telefonu
    'user_basket' => base64_encode($userBasket), // Sepet içeriği (base64)
    'debug_on' => $this->testMode ? '1' : '0',   // Debug modu
    'client_lang' => 'tr',                       // Dil
    'paytr_token' => $token,                     // HMAC-SHA256 token
    'no_installment' => $noInstallment,          // Taksit yok
    'max_installment' => $maxInstallment,        // Maksimum taksit
    'installment_count' => $installmentCount,    // Taksit sayısı
    'utoken' => $data['utoken'],                 // Kullanıcı token (kart saklama)
    'store_card' => $data['store_card'],         // Kart sakla flag
];
```

**HMAC-SHA256 Token Hesaplama**:
```php
$hashStr = $merchantId . $userIp . $merchantOid . $email .
           $paymentAmount . $paymentType . $installmentCount . $currency .
           ($testMode ? '1' : '0') . '0'; // non_3d

$token = base64_encode(hash_hmac('sha256', $hashStr . $merchantSalt, $merchantKey, true));
```

**Örnek İstek**:
```http
POST https://www.paytr.com/odeme/api/get-token
Content-Type: application/x-www-form-urlencoded

merchant_id=123456&user_ip=192.168.1.1&merchant_oid=ORDER_1698234567_1234&email=test@example.com&payment_type=card&payment_amount=10000&currency=TL&test_mode=1&non_3d=0&merchant_ok_url=https://yourdomain.com/payments/success&merchant_fail_url=https://yourdomain.com/payments/error&user_name=Test+User&user_address=Test+Address&user_phone=5551234567&user_basket=W1siUHJvZHVjdCIsIjEwMC4wMCIsMV1d&debug_on=1&client_lang=tr&paytr_token=abc123def456...&no_installment=1&max_installment=0&installment_count=0
```

**Başarılı Yanıt**:
```json
{
    "status": "success",
    "token": "abc123token456",
    "iframe_url": "https://www.paytr.com/odeme/guvenli/abc123token456"
}
```

**Hata Yanıtı**:
```json
{
    "status": "failed",
    "reason": "INVALID_MERCHANT_ID"
}
```

### 2. Credential Test İsteği

**Endpoint**: `POST https://www.paytr.com/odeme/api/get-token`

**Ne Zaman Atılır**: PayTR setup sayfasında "Bağlantıyı Test Et" butonuna basıldığında

**Dosya**: `app/Http/Controllers/PayTRSetupController.php:237`

```php
// Test İçin Minimal Parametreler
$testData = [
    'merchant_id' => $merchantId,
    'user_ip' => '127.0.0.1',
    'merchant_oid' => 'TEST_' . time(),
    'email' => 'test@example.com',
    'payment_amount' => '100',                    // 1 TRY
    'paytr_token' => $paytrToken,
    'user_basket' => base64_encode('Test item'),
    'debug_on' => '1',
    'no_installment' => '1',
    'max_installment' => '1',
    'user_name' => 'Test User',
    'user_address' => 'Test Address',
    'user_phone' => '5551234567',
    'merchant_ok_url' => config('app.url') . '/payments/success',
    'merchant_fail_url' => config('app.url') . '/payments/error',
    'timeout_limit' => '30',
    'currency' => 'TL',
    'test_mode' => $testMode,
];
```

### 3. Ödeme Durum Sorgulama

**Endpoint**: `POST https://www.paytr.com/odeme/durum-sorgu`

**Ne Zaman Atılır**: Ödeme durumu sorgulanırken

**Dosya**: `app/PaymentGateways/PayTRPaymentProvider.php:155`

```php
$requestData = [
    'merchant_id' => $this->merchantId,
    'merchant_oid' => $merchantOid,
    'paytr_token' => $token,
];

// Token hesaplama
$hashStr = $merchantOid . $this->merchantSalt;
$token = base64_encode(hash_hmac('sha256', $hashStr, $this->merchantKey, true));
```

### 4. İade İsteği

**Endpoint**: `POST https://www.paytr.com/odeme/iade`

**Ne Zaman Atılır**: Refund işlemi yapılırken

**Dosya**: `app/PaymentGateways/PayTRPaymentProvider.php:186`

```php
$requestData = [
    'merchant_id' => $this->merchantId,
    'merchant_oid' => $payment->merchant_oid,
    'return_amount' => $returnAmount,           // İade tutarı (kuruş)
    'paytr_token' => $token,
];

// Token hesaplama
$hashStr = $payment->merchant_oid . $returnAmount . $this->merchantSalt;
$token = base64_encode(hash_hmac('sha256', $hashStr, $this->merchantKey, true));
```

## 📥 PayTR'den Gelen Callback

**Endpoint**: `POST /payments/callback` (Bizim sistemimiz)

**Ne Zaman Gelir**: PayTR'de ödeme tamamlandığında

**Dosya**: `app/Http/Controllers/PaymentController.php:117`

```php
// PayTR'den gelen parametreler
$callbackData = [
    'merchant_oid' => 'ORDER_1698234567_1234',
    'status' => 'success',                      // success/failed
    'total_amount' => '10000',                  // Kuruş cinsinden
    'hash' => 'calculated_hash',                // HMAC-SHA256 hash
    'payment_id' => 'paytr_payment_12345',      // PayTR ödeme ID'si
    'failed_reason_msg' => 'Card declined',     // Hata durumunda
    'failed_reason_code' => '05',               // Hata kodu
    'utoken' => 'user_token_123',               // Kart saklama token'ı
    'ctoken' => 'card_token_456',               // Kart token'ı
    'card_type' => 'visa',                      // Kart tipi
    'card_last_four' => '4242',                 // Son 4 hane
    'card_brand' => 'visa',                     // Kart markası
];
```

**Callback Hash Doğrulama**:
```php
$calculatedHash = base64_encode(
    hash_hmac('sha256', 
        $merchantOid . $merchantSalt . $status . $totalAmount, 
        $merchantKey, 
        true
    )
);

if ($hash !== $calculatedHash) {
    // Geçersiz hash - işlem reddedilir
    return response('FAILED', 400);
}

// Başarılı işlem - PayTR'ye onay
return response('OK');
```

## 📊 Sistem Logları

### 1. Payment Logları

**Dosya**: `app/Logging/PaymentLogger.php`

**Log Dosyası**: `storage/logs/payments/YYYY-MM-DD.log`

```json
{
    "timestamp": "2024-01-15T10:30:00Z",
    "level": "info",
    "message": "Payment initialized",
    "context": {
        "payment_id": 123,
        "location_id": "loc_123",
        "merchant_oid": "ORDER_1698234567_1234",
        "transaction_id": "txn_456",
        "amount": 100.50,
        "currency": "TRY",
        "provider": "paytr",
        "test_mode": true,
        "user_ip": "192.168.1.1",
        "email": "customer@example.com"
    },
    "extra": {
        "request_id": "req_789",
        "user_agent": "Mozilla/5.0...",
        "paytr_token": "abc123...",
        "iframe_url": "https://www.paytr.com/odeme/guvenli/abc123..."
    }
}
```

### 2. Webhook Logları

**Dosya**: `app/Logging/WebhookLogger.php`

**Veritabanı**: `webhook_logs` tablosu

```json
{
    "id": 1,
    "type": "incoming",
    "source": "paytr_callback",
    "event": "payment.completed",
    "payload": {
        "merchant_oid": "ORDER_1698234567_1234",
        "status": "success",
        "total_amount": "10000",
        "payment_id": "paytr_payment_12345",
        "hash": "calculated_hash"
    },
    "response": {
        "status": "OK",
        "processed": true,
        "payment_updated": true
    },
    "status": "success",
    "http_status_code": 200,
    "received_at": "2024-01-15T10:30:00Z",
    "processed_at": "2024-01-15T10:30:01Z"
}
```

### 3. User Activity Logları

**Dosya**: `app/Logging/UserActionLogger.php`

**Veritabanı**: `user_activity_logs` tablosu

```json
{
    "id": 1,
    "hl_account_id": 1,
    "location_id": "loc_123",
    "user_id": "user_456",
    "action": "paytr_configured",
    "resource_type": "payment_provider",
    "resource_id": "paytr",
    "metadata": {
        "merchant_id": "123456",
        "test_mode": true,
        "configured_at": "2024-01-15T10:30:00Z"
    },
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0...",
    "created_at": "2024-01-15T10:30:00Z"
}
```

### 4. Payment Failure Logları

**Veritabanı**: `payment_failures` tablosu

```json
{
    "id": 1,
    "payment_id": 123,
    "hl_account_id": 1,
    "location_id": "loc_123",
    "merchant_oid": "ORDER_1698234567_1234",
    "transaction_id": "txn_456",
    "provider": "paytr",
    "error_code": "05",
    "error_message": "Card declined",
    "failure_reason": "Insufficient funds",
    "request_data": {
        "amount": 100.50,
        "currency": "TRY",
        "card_last_four": "4242"
    },
    "response_data": {
        "status": "failed",
        "failed_reason_code": "05",
        "failed_reason_msg": "Card declined"
    },
    "user_ip": "192.168.1.1",
    "created_at": "2024-01-15T10:30:00Z"
}
```

## 🔧 Hata Ayıklama

### PayTR API Hataları

| Hata Kodu | Açıklama | Çözüm |
|-----------|----------|-------|
| `INVALID_MERCHANT_ID` | Geçersiz Merchant ID | Merchant ID'yi kontrol edin |
| `INVALID_HASH` | Geçersiz hash | Hash hesaplamasını kontrol edin |
| `INVALID_AMOUNT` | Geçersiz tutar | Tutar kuruş cinsinden olmalı |
| `INVALID_EMAIL` | Geçersiz email | Email formatını kontrol edin |
| `INSUFFICIENT_FUNDS` | Yetersiz bakiye | Müşteri kartında yeterli bakiye yok |
| `CARD_DECLINED` | Kart reddedildi | Müşteri farklı kart denemeli |

### Log Analizi Komutları

```bash
# Son ödeme loglarını görüntüle
tail -f storage/logs/payments/$(date +%Y-%m-%d).log

# PayTR hatalarını filtrele
grep "PayTR" storage/logs/laravel.log | grep "ERROR"

# Başarısız ödeme callback'lerini bul
grep "PAYTR notification failed" storage/logs/laravel.log

# Webhook başarısızlıklarını görüntüle
php artisan tinker
> WebhookLog::where('status', 'failed')->latest()->take(10)->get()

# Belirli bir merchant_oid için logları bul
grep "ORDER_1698234567_1234" storage/logs/laravel.log

# Test mode işlemlerini filtrele
grep "test_mode.*true" storage/logs/payments/*.log
```

### Veritabanı Sorguları

```sql
-- Son 24 saatteki başarısız ödemeler
SELECT * FROM payment_failures 
WHERE created_at >= NOW() - INTERVAL 24 HOUR 
ORDER BY created_at DESC;

-- PayTR konfigürasyonu olmayan hesaplar
SELECT location_id, paytr_configured, paytr_configured_at 
FROM hl_accounts 
WHERE paytr_configured = false OR paytr_configured IS NULL;

-- Son 7 gündeki ödeme istatistikleri
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(amount) as total_amount
FROM payments 
WHERE created_at >= NOW() - INTERVAL 7 DAY 
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Webhook delivery durumu
SELECT 
    source,
    status,
    COUNT(*) as count
FROM webhook_logs 
WHERE created_at >= NOW() - INTERVAL 24 HOUR 
GROUP BY source, status;
```

## 🔍 Monitoring ve Alerting

### Kritik Metrikler

1. **Payment Success Rate**: Son 1 saatteki ödeme başarı oranı
2. **API Response Time**: PayTR API yanıt süreleri
3. **Webhook Delivery**: Webhook delivery başarı oranı
4. **Error Rate**: Hata oranları ve tipleri

### Alert Conditions

```php
// Ödeme başarı oranı %90'ın altına düştüyse
$successRate = Payment::where('created_at', '>=', now()->subHour())
    ->where('status', 'success')
    ->count() / Payment::where('created_at', '>=', now()->subHour())->count();

if ($successRate < 0.9) {
    // Alert gönder
}

// PayTR API yanıt süresi 5 saniyeyi geçtiyse
if ($apiResponseTime > 5000) {
    // Alert gönder
}

// Son 10 dakikada 5'ten fazla webhook hatası varsa
$webhookErrors = WebhookLog::where('status', 'failed')
    ->where('created_at', '>=', now()->subMinutes(10))
    ->count();

if ($webhookErrors > 5) {
    // Alert gönder
}
```

## 📈 Performans İyileştirmeleri

### 1. Cache Stratejisi

```php
// PayTR credentials cache
Cache::remember("paytr_credentials_{$locationId}", 3600, function() use ($account) {
    return $account->getPayTRCredentials();
});

// Payment methods cache
Cache::remember("payment_methods_{$contactId}", 1800, function() use ($contactId) {
    return PaymentMethod::where('contact_id', $contactId)->active()->get();
});
```

### 2. Queue İşlemleri

```php
// Webhook gönderimini queue'ya al
dispatch(new SendHighLevelWebhookJob($payment))->onQueue('webhooks');

// Log yazma işlemini queue'ya al
dispatch(new LogPaymentActivityJob($logData))->onQueue('logging');
```

### 3. Database İndeksler

```sql
-- Performans için önemli indeksler
CREATE INDEX idx_payments_merchant_oid ON payments(merchant_oid);
CREATE INDEX idx_payments_transaction_id ON payments(transaction_id);
CREATE INDEX idx_payments_status_created ON payments(status, created_at);
CREATE INDEX idx_webhook_logs_source_status ON webhook_logs(source, status);
CREATE INDEX idx_payment_failures_created ON payment_failures(created_at);
```

Bu dokümantasyon PayTR API entegrasyonunun tüm teknik detaylarını ve log yapısını kapsar. Sistem monitörü ve hata ayıklama için kullanılabilir.