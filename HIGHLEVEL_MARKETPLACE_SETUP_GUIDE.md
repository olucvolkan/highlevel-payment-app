# HighLevel Marketplace'de PayTR Uygulaması Kurulum ve Test Rehberi

## İçindekiler
1. [HighLevel Developer Hesabı Oluşturma](#1-highlevel-developer-hesabı-oluşturma)
2. [Marketplace Uygulaması Oluşturma](#2-marketplace-uygulaması-oluşturma)
3. [Uygulama Yapılandırması](#3-uygulama-yapılandırması)
4. [OAuth ve Webhook Ayarları](#4-oauth-ve-webhook-ayarları)
5. [Custom Payment Provider Ayarları](#5-custom-payment-provider-ayarları)
6. [Test Ortamı Hazırlığı](#6-test-ortamı-hazırlığı)
7. [Uygulamayı Test Etme](#7-uygulamayı-test-etme)
8. [Prodüksiyon Yayını](#8-prodüksiyon-yayını)

---

## 1. HighLevel Developer Hesabı Oluşturma

### Adım 1.1: HighLevel Hesabı Oluşturun
1. [HighLevel](https://www.gohighlevel.com/) web sitesine gidin
2. "Start Free Trial" veya "Sign Up" butonuna tıklayın
3. Hesap bilgilerinizi girin ve hesabınızı oluşturun
4. Email doğrulamasını tamamlayın

### Adım 1.2: Developer Erişimi İsteyin
1. HighLevel hesabınıza giriş yapın
2. [HighLevel Marketplace Developer Portal](https://marketplace.gohighlevel.com/developers) sayfasına gidin
3. Developer erişimi için başvurun (eğer henüz erişiminiz yoksa)
4. Developer Agreement'ı okuyun ve kabul edin

> **Not**: Developer erişimi genellikle 24-48 saat içinde onaylanır.

---

## 2. Marketplace Uygulaması Oluşturma

### Adım 2.1: Yeni Uygulama Oluşturun
1. [Marketplace Developer Console](https://marketplace.gohighlevel.com/developers) sayfasına gidin
2. **"Create New App"** veya **"New Application"** butonuna tıklayın
3. Temel bilgileri doldurun:

```
App Name: PayTR Payment Gateway
App Type: Public
Category: Payments
Description: PayTR ile Türkiye'deki işletmeler için güvenli ödeme işlemleri
```

### Adım 2.2: Uygulama Detaylarını Girin

**Genel Bilgiler:**
- **App Name**: `PayTR Payment Gateway`
- **App Description**:
  ```
  PayTR entegrasyonu ile Türkiye'deki müşterilerinizden güvenli bir şekilde
  ödeme alın. Kredi kartı, banka kartı ve taksitli ödeme seçenekleri.
  ```
- **App Icon**: 512x512 PNG logo yükleyin
- **App Screenshots**: En az 3 ekran görüntüsü yükleyin
- **Support Email**: `support@yourdomain.com`
- **Privacy Policy URL**: `https://yourdomain.com/privacy`
- **Terms of Service URL**: `https://yourdomain.com/terms`

---

## 3. Uygulama Yapılandırması

### Adım 3.1: Distribution Settings

**Distribution Type:**
- Public App (Marketplace'de herkesin görebileceği)
- Private App (Sadece belirli hesaplar için)

Test aşamasında **Private** seçin, prodüksiyonda **Public** yapın.

**Pricing:**
- Free
- One-time payment
- Subscription

Test için **Free** seçin.

### Adım 3.2: Supported Scopes (OAuth İzinleri)

Aşağıdaki izinleri seçin:

```
✅ payments/orders.readonly
✅ payments/orders.write
✅ payments/subscriptions.readonly
✅ payments/transactions.readonly
✅ payments/custom-provider.readonly
✅ payments/custom-provider.write
✅ products.readonly
✅ products/prices.readonly
✅ locations.readonly (isteğe bağlı)
✅ contacts.readonly (isteğe bağlı, müşteri bilgileri için)
```

> **Önemli**: Custom payment provider için `payments/custom-provider.readonly` ve `payments/custom-provider.write` izinleri zorunludur.

---

## 4. OAuth ve Webhook Ayarları

### Adım 4.1: OAuth Configuration

**Redirect URLs (Callback URLs):**
```
Local Test: http://localhost:8000/oauth/callback
Ngrok Test: https://YOUR-NGROK-ID.ngrok.io/oauth/callback
Production: https://yourdomain.com/oauth/callback
```

**Scopes (yukarıda seçtiğiniz izinler otomatik gelecektir)**

### Adım 4.2: Webhook Configuration

HighLevel'dan gelen webhook'lar için:

```
Webhook URL (Local): http://localhost:8000/webhooks/marketplace
Webhook URL (Ngrok): https://YOUR-NGROK-ID.ngrok.io/webhooks/marketplace
Webhook URL (Production): https://yourdomain.com/webhooks/marketplace
```

**Webhook Events:**
- `app.installed` - Uygulama kurulduğunda
- `app.uninstalled` - Uygulama kaldırıldığında
- `location.created` - Yeni lokasyon oluşturulduğunda
- `payment.created` - Yeni ödeme oluşturulduğunda

### Adım 4.3: Client Credentials

Uygulamayı kaydettikten sonra size verilecek:
```
Client ID: xxxxxxxxxxxxxxxxxxxx
Client Secret: yyyyyyyyyyyyyyyyyyyy
```

Bu bilgileri `.env` dosyanıza kaydedin:
```env
HIGHLEVEL_CLIENT_ID=xxxxxxxxxxxxxxxxxxxx
HIGHLEVEL_CLIENT_SECRET=yyyyyyyyyyyyyyyyyyyy
```

---

## 5. Custom Payment Provider Ayarları

### Adım 5.1: Payment Provider Configuration

Marketplace Developer Console'da **Payment Provider** sekmesine gidin:

**Provider Name:**
```
PayTR
```

**Provider Type:**
```
Custom Payment Provider
```

**Payment Methods Supported:**
- ✅ Credit Card
- ✅ Debit Card
- ✅ Bank Transfer (isteğe bağlı)

### Adım 5.2: Payment URLs

**Query URL** (HighLevel'ın ödeme durumunu sorguladığı endpoint):
```
Local: http://localhost:8000/api/payments/query
Ngrok: https://YOUR-NGROK-ID.ngrok.io/api/payments/query
Production: https://yourdomain.com/api/payments/query
```

**Payment Page URL** (İframe içinde gösterilecek ödeme sayfası):
```
Local: http://localhost:8000/payments/page
Ngrok: https://YOUR-NGROK-ID.ngrok.io/payments/page
Production: https://yourdomain.com/payments/page
```

**Webhook URL** (PayTR callback'leri için):
```
Local: http://localhost:8000/webhooks/paytr
Ngrok: https://YOUR-NGROK-ID.ngrok.io/webhooks/paytr
Production: https://yourdomain.com/webhooks/paytr
```

### Adım 5.3: Supported Operations

Aşağıdaki işlemleri desteklediğinizi işaretleyin:
- ✅ `one-time payment` - Tek seferlik ödeme
- ✅ `subscription` - Abonelik ödemeleri
- ✅ `refund` - İade işlemleri
- ✅ `capture` - Ödeme yakalama
- ✅ `card_storage` - Kart saklama (isteğe bağlı)

---

## 6. Test Ortamı Hazırlığı

### Adım 6.1: Local Development Setup

**1. Laravel Uygulamasını Başlatın:**
```bash
# PostgreSQL ve Laravel'i başlat
docker-compose up -d
php artisan serve
```

**2. Ngrok ile Tunnel Açın:**
```bash
# Ngrok'u başlat (port 8000'i dış dünyaya açar)
ngrok http 8000
```

Ngrok size şöyle bir URL verecektir:
```
https://abc123def.ngrok.io -> http://localhost:8000
```

**3. HighLevel Marketplace'de URL'leri Güncelleyin:**

Marketplace Developer Console'a girin ve tüm URL'leri Ngrok URL'iniz ile güncelleyin:
```
Redirect URL: https://abc123def.ngrok.io/oauth/callback
Webhook URL: https://abc123def.ngrok.io/webhooks/marketplace
Query URL: https://abc123def.ngrok.io/api/payments/query
Payment Page URL: https://abc123def.ngrok.io/payments/page
```

### Adım 6.2: PayTR Test Hesabı

**1. PayTR Test Merchant Hesabı:**
- [PayTR Test Portal](https://www.paytr.com/magaza/entegrasyon)
- Test merchant ID ve keys alın

**2. Test Credentials'ları .env'e Ekleyin:**
```env
PAYTR_MERCHANT_ID=test_merchant_id
PAYTR_MERCHANT_KEY=test_merchant_key
PAYTR_MERCHANT_SALT=test_merchant_salt
PAYTR_TEST_MODE=1
```

### Adım 6.3: Database Migration

```bash
php artisan migrate:fresh --seed
```

---

## 7. Uygulamayı Test Etme

### Adım 7.1: Uygulamayı HighLevel'a Yükleme

**Test Location Oluşturma:**
1. HighLevel hesabınıza giriş yapın
2. Sol menüden **Settings → Locations** gidin
3. **Create New Location** tıklayın
4. Test lokasyonu oluşturun (örn: "Test Restaurant")

**Uygulamayı Yükleme:**
1. HighLevel'da **Marketplace** sekmesine gidin
2. **My Apps** veya **Installed Apps** bölümüne gidin
3. Developer Console'daki uygulamanızı bulun (Private ise link üzerinden erişin)
4. **Install App** butonuna tıklayın
5. Location seçin ve izinleri onaylayın

### Adım 7.2: OAuth Flow Testi

**Beklenen Akış:**
```
1. User clicks "Install App" on HighLevel
2. HighLevel redirects to your OAuth consent page
3. User approves permissions
4. HighLevel redirects to: /oauth/callback?code=xxx
5. Your app exchanges code for access_token
6. Store access_token in database
7. Redirect user back to HighLevel
```

**Kontrol Edilecekler:**
```bash
# Laravel log'larını takip edin
tail -f storage/logs/laravel.log

# PostgreSQL'de token'ın kaydedildiğini kontrol edin
docker exec -it highlevel-postgres psql -U laravel -d highlevel_payments
SELECT * FROM hl_accounts;
```

### Adım 7.3: Payment Provider Kurulumu

**HighLevel'da Payment Settings:**
1. Location'ınıza gidin
2. **Settings → Payments** menüsüne gidin
3. **Payment Integrations** sekmesini açın
4. **PayTR** provider'ını bulun ve **Connect** tıklayın
5. PayTR merchant bilgilerinizi girin (eğer setup sayfanız varsa)

### Adım 7.4: İlk Test Ödemesi

**Test Invoice/Order Oluşturma:**
1. HighLevel'da **Payments → Invoices** gidin
2. **Create Invoice** tıklayın
3. Test müşterisi seçin veya oluşturun
4. Ürün/hizmet ekleyin (örn: 100 TRY)
5. **Send Invoice** tıklayın

**Ödeme Sayfası Testi:**
1. Invoice'daki **Pay Now** linkine tıklayın
2. PayTR ödeme iframe'i yüklenmeli
3. Test kartı ile ödeme yapın:
   ```
   Kart Numarası: 4508034508034509
   SKT: 12/26
   CVV: 000
   3D Secure Şifre: 12345
   ```
4. Ödemenin başarılı olduğunu kontrol edin

**Backend Log Kontrolü:**
```bash
# Webhook callback'lerini kontrol edin
tail -f storage/logs/webhooks/$(date +%Y-%m-%d).log

# Payment transaction'ları kontrol edin
docker exec -it highlevel-postgres psql -U laravel -d highlevel_payments
SELECT * FROM payments ORDER BY created_at DESC LIMIT 5;
```

### Adım 7.5: Webhook Testi

**PayTR Callback Testi:**
```bash
# Postman veya curl ile test webhook gönderin
curl -X POST https://YOUR-NGROK-ID.ngrok.io/webhooks/paytr \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "merchant_oid=TEST123&status=success&total_amount=10000&hash=xxx"
```

**HighLevel Webhook Gönderimi:**
Ödeme başarılı olduğunda Laravel app'iniz HighLevel'a webhook göndermeli:
```php
POST https://backend.leadconnectorhq.com/payments/custom-provider/webhook
{
  "type": "payment.captured",
  "locationId": "xxx",
  "chargeId": "charge_123",
  "amount": 100.00,
  "currency": "TRY"
}
```

### Adım 7.6: Query Endpoint Testi

HighLevel zaman zaman ödeme durumunu sorgulayabilir:

```bash
# Test query request (HighLevel'dan gelecek)
curl -X POST https://YOUR-NGROK-ID.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -d '{
    "type": "verify",
    "chargeId": "charge_123",
    "locationId": "loc_xxx"
  }'
```

**Beklenen Response:**
```json
{
  "success": true,
  "status": "succeeded",
  "amount": 100.00,
  "currency": "TRY",
  "transactionId": "paytr_merchant_oid_xxx"
}
```

---

## 8. Prodüksiyon Yayını

### Adım 8.1: Production Hazırlık

**1. Environment Variables:**
```env
APP_ENV=production
APP_DEBUG=false
PAYTR_TEST_MODE=0
PAYTR_MERCHANT_ID=your_live_merchant_id
PAYTR_MERCHANT_KEY=your_live_merchant_key
PAYTR_MERCHANT_SALT=your_live_merchant_salt
```

**2. SSL Certificate:**
- Domain'iniz için SSL sertifikası alın (Let's Encrypt veya Cloudflare)
- HTTPS zorunludur

**3. Database Backup:**
```bash
# Production database yedekleme
pg_dump -U laravel highlevel_payments > backup_$(date +%Y%m%d).sql
```

### Adım 8.2: Marketplace URL Güncelleme

Developer Console'da tüm URL'leri production URL'iniz ile değiştirin:
```
Redirect URL: https://yourdomain.com/oauth/callback
Webhook URL: https://yourdomain.com/webhooks/marketplace
Query URL: https://yourdomain.com/api/payments/query
Payment Page URL: https://yourdomain.com/payments/page
```

### Adım 8.3: Marketplace Submission

**Review Checklist:**
- ✅ Tüm URL'ler çalışıyor ve HTTPS
- ✅ OAuth flow test edildi
- ✅ Test ödemeleri başarılı
- ✅ Webhook'lar düzgün çalışıyor
- ✅ Error handling test edildi
- ✅ Logo ve screenshots yüklendi
- ✅ Privacy policy ve Terms mevcut
- ✅ Support email aktif

**Submit for Review:**
1. Developer Console'da **Submit for Review** tıklayın
2. Review notları ekleyin
3. Test account credentials sağlayın (eğer istenirse)
4. HighLevel review team onaylamasını bekleyin (3-7 gün)

### Adım 8.4: Go Live

Onay aldıktan sonra:
1. **Distribution Type** → **Public** yapın
2. **Status** → **Published** olarak ayarlayın
3. Uygulamanız Marketplace'de görünür olacaktır

---

## Troubleshooting (Sorun Giderme)

### OAuth Sorunları

**Hata: "Invalid redirect_uri"**
- Developer Console'daki redirect URL ile kodunuzdaki URL'nin birebir eşleştiğinden emin olun
- URL sonunda `/` olmamalı

**Hata: "Invalid client_id"**
- `.env` dosyasında `HIGHLEVEL_CLIENT_ID` doğru mu kontrol edin
- Config cache'i temizleyin: `php artisan config:clear`

### Webhook Sorunları

**Webhook gelmiyorsa:**
```bash
# Ngrok console'u açın
http://localhost:4040

# Gelen istekleri görebilirsiniz
```

**Webhook hash doğrulama hatası:**
- PayTR merchant_salt doğru mu kontrol edin
- Hash generation logic'i PayTR documentation ile karşılaştırın

### Payment Iframe Sorunları

**Iframe yüklenmiyorsa:**
- X-Frame-Options header'ını kaldırın
- CORS ayarlarını kontrol edin:
  ```php
  // config/cors.php
  'supports_credentials' => true,
  'allowed_origins' => ['https://app.gohighlevel.com']
  ```

**postMessage çalışmıyorsa:**
- Browser console'da JavaScript hataları kontrol edin
- Parent window origin'i doğrulayın

---

## Test Checklist

### Kurulum Testi
- [ ] Uygulama HighLevel'a başarıyla yüklendi
- [ ] OAuth akışı tamamlandı ve token kaydedildi
- [ ] Webhook endpoint'i erişilebilir

### Ödeme Testi
- [ ] Invoice oluşturuldu
- [ ] Payment page iframe yüklendi
- [ ] Test kartı ile ödeme başarılı
- [ ] HighLevel'a webhook gönderildi
- [ ] Payment status doğru şekilde güncellendi

### API Endpoint Testi
- [ ] Query endpoint yanıt veriyor
- [ ] Refund endpoint çalışıyor
- [ ] Error handling düzgün

### Security Testi
- [ ] Hash/signature doğrulama çalışıyor
- [ ] API keys gizli kalıyor
- [ ] HTTPS zorunlu kılınıyor

---

## Faydalı Linkler

- [HighLevel Marketplace Docs](https://highlevel.stoplight.io/docs/integrations/)
- [HighLevel OAuth Guide](https://highlevel.stoplight.io/docs/integrations/docs/oauth/oauth-flow.md)
- [PayTR API Documentation](https://www.paytr.com/magaza/entegrasyon)
- [Ngrok Documentation](https://ngrok.com/docs)

---

## Destek

Sorularınız için:
- **Email**: support@yourdomain.com
- **GitHub Issues**: [Proje Repository](https://github.com/yourrepo)
- **Slack Community**: HighLevel Developers Slack

---

**Son Güncelleme**: 2025-01-04
**Versiyon**: 1.0.0
