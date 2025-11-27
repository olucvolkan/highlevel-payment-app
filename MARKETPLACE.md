# HighLevel Marketplace Integration Guide

## marketplace.json Dosyası

Bu dosya HighLevel Marketplace'e uygulama submit ederken kullanılır.

### Dosya Konumu
```
/marketplace.json
```

### İçerik Açıklaması

#### 1. **Temel Bilgiler**
```json
{
  "name": "PayTR - Yerel Ödeme",
  "slug": "paytr-yerel-odeme",
  "type": "PAYMENTS",
  "version": "1.0.0"
}
```

#### 2. **OAuth Ayarları**
```json
"auth": {
  "type": "OAUTH2",
  "redirect_uri": "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/oauth/callback",
  "scopes": [
    "payments/orders.readonly",
    "payments/orders.write",
    "payments/subscriptions.readonly",
    "payments/transactions.readonly",
    "payments/custom-provider.readonly",
    "payments/custom-provider.write",
    "products.readonly",
    "products/prices.readonly"
  ]
}
```

**Scope'lar:**
- `payments/orders.*` - Sipariş okuma/yazma
- `payments/subscriptions.*` - Abonelik yönetimi
- `payments/transactions.*` - İşlem geçmişi
- `payments/custom-provider.*` - Custom payment provider API
- `products.*` - Ürün ve fiyat bilgileri

#### 3. **Settings Page**
```json
"settings": {
  "pages": [
    {
      "title": "Settings",
      "path": "/settings",
      "iframe": "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/paytr/setup?iframe=1"
    }
  ]
}
```

**Önemli:**
- `?iframe=1` parametresi eklenmelidir
- Backend bu parametreyi kontrol ederek `setup-highlevel.blade.php` view'ını gösterir

#### 4. **Webhook Endpoints**
```json
"webhooks": {
  "install": "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/webhooks/marketplace",
  "uninstall": "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/oauth/uninstall"
}
```

**Events:**
- `install` - Kullanıcı uygulamayı yüklediğinde
- `uninstall` - Kullanıcı uygulamayı kaldırdığında

#### 5. **Payment Integration**
```json
"payment": {
  "queryUrl": "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/payments/query",
  "paymentsUrl": "https://yerelodeme-payment-app-master-a645wy.laravel.cloud/payments/page",
  "supportedCurrencies": ["TRY", "USD", "EUR"],
  "supportedPaymentMethods": ["CREDIT_CARD", "DEBIT_CARD"],
  "features": {
    "installments": true,
    "subscriptions": true,
    "refunds": true,
    "cardStorage": true
  }
}
```

**Endpoints:**
- `queryUrl` - HighLevel'ın ödeme durumunu sorguladığı endpoint
- `paymentsUrl` - Ödeme iframe'inin yüklendiği sayfa

## HighLevel Marketplace'e Submit Etme

### 1. Ön Hazırlık

**Gerekli Materyaller:**
- [ ] App icon (512x512 PNG)
- [ ] Screenshot'lar (1280x720 PNG)
- [ ] Logo (256x256 PNG)
- [ ] marketplace.json dosyası

**Test Edilmesi Gerekenler:**
- [ ] OAuth flow çalışıyor
- [ ] Settings sayfası iframe içinde açılıyor
- [ ] location_id backend'den geliyor
- [ ] PayTR credentials kaydediliyor
- [ ] Test payment başarılı
- [ ] Webhook'lar çalışıyor

### 2. Marketplace Dashboard

1. **HighLevel Marketplace Developer Portal**'a gidin
   - URL: https://marketplace.gohighlevel.com/

2. **Create New App** butonuna tıklayın

3. **App Type**: `Payment Provider` seçin

4. **Basic Information** formunu doldurun:
   - App Name: `PayTR - Yerel Ödeme`
   - Slug: `paytr-yerel-odeme`
   - Category: `Payments`
   - Short Description: "Accept payments from Turkish customers using PayTR"

5. **OAuth Configuration**:
   - Client ID: `68f8e7f079717a0cecaef38a-mh6gs7pg`
   - Client Secret: `adc155bb-9088-4186-b8d8-9d89f6ca2e5d`
   - Redirect URI: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud/oauth/callback`
   - Scopes: marketplace.json'dan kopyalayın

6. **Settings Page URL**:
   ```
   https://yerelodeme-payment-app-master-a645wy.laravel.cloud/paytr/setup?iframe=1
   ```

7. **Payment Integration**:
   - Query URL: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/payments/query`
   - Payments URL: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud/payments/page`

8. **Webhooks**:
   - Install webhook: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud/api/webhooks/marketplace`
   - Uninstall webhook: `https://yerelodeme-payment-app-master-a645wy.laravel.cloud/oauth/uninstall`

### 3. Testing

**Test Account ile Test:**
1. HighLevel test account oluşturun
2. Marketplace'den uygulamanızı "Install" edin
3. OAuth akışını tamamlayın
4. Settings sayfasında PayTR credentials girin
5. Test payment yapın
6. Webhook'ların çalıştığını kontrol edin

**Test Checklist:**
- [ ] Install webhook received
- [ ] OAuth redirect başarılı
- [ ] Settings iframe açıldı
- [ ] location_id doğru geldi
- [ ] Credentials kaydedildi
- [ ] Payment iframe yüklendi
- [ ] Payment başarılı
- [ ] Webhook to HighLevel sent
- [ ] Uninstall webhook received

### 4. Production Deployment

**Environment Variables:**
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yerelodeme-payment-app-master-a645wy.laravel.cloud

HIGHLEVEL_CLIENT_ID=your-production-client-id
HIGHLEVEL_CLIENT_SECRET=your-production-client-secret
```

**Laravel Cloud Deployment:**
```bash
# Deploy to production
git push production master

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Marketplace Submission

1. **App Review Checklist**:
   - [ ] All endpoints HTTPS
   - [ ] OAuth working
   - [ ] Webhooks responding within 5 seconds
   - [ ] Error handling implemented
   - [ ] Logging configured
   - [ ] Documentation complete

2. **Submit for Review**:
   - marketplace.json upload edin
   - Screenshots ekleyin
   - Privacy policy URL'i ekleyin
   - Terms of service URL'i ekleyin

3. **Review Process**:
   - HighLevel team review yapacak
   - Test yapacaklar
   - Feedback verecekler
   - Approve edecekler

## Güncellemeler

### Version Update

marketplace.json'da version'ı güncelleyin:
```json
{
  "version": "1.1.0"
}
```

### Changelog

Önemli değişiklikleri dokümante edin:
```
v1.1.0 (2024-01-15)
- Added installment support
- Improved error handling
- Fixed location_id extraction

v1.0.0 (2024-01-01)
- Initial release
- PayTR Direct API integration
- OAuth authentication
```

## Troubleshooting

### OAuth Redirect Not Working
**Sorun**: OAuth callback 404 hatası veriyor
**Çözüm**:
```bash
# Redirect URI'yi kontrol et
echo $HIGHLEVEL_REDIRECT_URI

# Route'ları kontrol et
php artisan route:list | grep oauth
```

### Settings Page Iframe Issue
**Sorun**: Settings sayfası yüklenmiyor
**Çözüm**:
```bash
# iframe parametresini kontrol et
curl https://yerelodeme-payment-app-master-a645wy.laravel.cloud/paytr/setup?iframe=1

# Logs kontrol et
tail -f storage/logs/laravel.log
```

### location_id Not Found
**Sorun**: Backend location_id bulamıyor
**Çözüm**:
- Referrer header'ı kontrol et
- Database'de hl_accounts tablosunu kontrol et
- Log'lara bak: `PayTR Setup page accessed`

## Support

**Developer Contact:**
- Email: volkanoluc@gmail.com
- Documentation: [README.md](./README.md)
- Technical Docs: [CLAUDE.md](./CLAUDE.md)
