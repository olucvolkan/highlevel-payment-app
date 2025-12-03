# HighLevel Config Endpoint Fix

## Problem
❌ Yanlış endpoint kullanılıyordu: `/payments/custom-provider/config`
✅ Doğru endpoint: `/payments/custom-provider/connect`

## Yapılan Değişiklikler

### 1. HighLevelService::connectConfig() Güncellendi

**File**: `app/Services/HighLevelService.php` (line 251)

#### Değişiklikler:
1. **Endpoint URL düzeltildi**:
   - Eski: `$this->apiUrl . '/payments/custom-provider/config'`
   - Yeni: `$this->apiUrl . '/payments/custom-provider/connect'`

2. **locationId query parameter olarak eklendi**:
   - URL build edildi: `$url = $this->apiUrl . '/payments/custom-provider/connect?locationId=' . $locationId`
   - `http_build_query()` kullanılarak proper encoding yapıldı

3. **Payload format düzeltildi**:
   - `locationId` payload'dan kaldırıldı (artık query parameter'da)
   - Key isimleri değiştirildi:
     - `testMode` → `test`
     - `liveMode` → `live`

#### Eski Payload ❌:
```php
// URL
POST /payments/custom-provider/config

// Body
[
    'locationId' => 'loc_123',  // ❌ Body'de
    'testMode' => [             // ❌ Yanlış key
        'apiKey' => '...',
        'publishableKey' => '...'
    ],
    'liveMode' => [              // ❌ Yanlış key
        'apiKey' => '...',
        'publishableKey' => '...'
    ]
]
```

#### Yeni Format (HighLevel API'ye uygun) ✅:
```php
// URL - locationId query parameter olarak
POST /payments/custom-provider/connect?locationId=loc_123

// Body - Sadece credentials
[
    'test' => [                  // ✅ Doğru key
        'apiKey' => '...',
        'publishableKey' => '...'
    ],
    'live' => [                  // ✅ Doğru key
        'apiKey' => '...',
        'publishableKey' => '...'
    ]
]
```

### 2. Log Mesajları Güncellendi

**Değişiklikler**:
- Endpoint referansları `/config` → `/connect` olarak değiştirildi
- Log mesajlarında yeni payload yapısı gösteriliyor

## Doğru cURL Örneği (HighLevel Docs'tan)

**⚠️ ÖNEMLİ**: `locationId` **query parameter** olarak gitmeli!

```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  // locationId query parameter olarak URL'de
  CURLOPT_URL => 'https://services.leadconnectorhq.com/payments/custom-provider/connect?locationId=YOUR_LOCATION_ID',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>'{
    "live": {
      "apiKey": "y5ZQxryRFXZHvUJZdLeXXXXX",
      "publishableKey": "rzp_test_zPRoVMLOa0XXXX"
    },
    "test": {
      "apiKey": "y5ZQxryRFXZHvUJZdLeXXXXX",
      "publishableKey": "rzp_test_zPRoVMLOa0XXXX"
    }
  }',
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer <TOKEN>'
  ),
));

$response = curl_exec($curl);
curl_close($curl);
echo $response;
```

**URL Yapısı**:
```
https://services.leadconnectorhq.com/payments/custom-provider/connect?locationId={locationId}
```

## Önemli Notlar

### 1. locationId Nerede?
- ✅ `locationId` **query parameter** olarak URL'de
- ❌ Payload body'de DEĞİL
- Format: `?locationId={location_id}`

### 2. Token Type Kontrolü
Controller'da config oluşturmadan önce token type'ı kontrol etmeliyiz:

```php
// HighLevelProviderController::saveCredentials içinde
if ($account->needsLocationTokenExchange()) {
    $this->highLevelService->exchangeCompanyTokenForLocation(
        $account,
        $account->location_id
    );
    $account->refresh();
}

// Şimdi location token ile config oluştur
$configResult = $this->highLevelService->connectConfig($account, [
    'liveMode' => [...],
    'testMode' => [...]
]);
```

### 3. Backend vs API Payload Farkı

**Backend (Controller → Service)**: Okunabilirlik için:
```php
$this->highLevelService->connectConfig($account, [
    'liveMode' => [...],    // Daha açıklayıcı
    'testMode' => [...]
]);
```

**Service → HighLevel API**: API formatına uygun:
```php
$payload = [
    'live' => $config['liveMode'],  // API'nin beklediği format
    'test' => $config['testMode']
];
```

## Test Etme

### 1. Log'larda Doğru Endpoint Kontrolü
```bash
tail -f storage/logs/laravel.log | grep "payments/custom-provider"
```

Görmemiz gereken:
```
Creating HighLevel config via /connect endpoint
endpoint: https://services.leadconnectorhq.com/payments/custom-provider/connect
```

### 2. Payload Format Kontrolü
Log'da şunu görmemiz lazım:
```json
{
  "payload_keys": ["test", "live"]
}
```

NOT: `["testMode", "liveMode", "locationId"]` gibi bir şey görüyorsanız, hala eski format kullanılıyor demektir.

### 3. API Response Kontrolü

**Başarılı Response**:
```json
{
  "_id": "config_abc123",
  "locationId": "loc_xyz789",
  "createdAt": "2024-12-03T..."
}
```

**Hatalı Response (Endpoint Yanlışsa)**:
```json
{
  "statusCode": 404,
  "message": "Route not found"
}
```

## Olası Hatalar ve Çözümleri

### Hata 1: 404 Not Found
**Sebep**: Yanlış endpoint (`/config` yerine `/connect`)
**Çözüm**: ✅ Yukarıdaki değişiklikler ile düzeltildi

### Hata 2: 401 Unauthorized
**Sebep**: Company token kullanılıyor, Location token gerekli
**Çözüm**: Token exchange yap
```php
$this->highLevelService->exchangeCompanyTokenForLocation($account, $locationId);
```

### Hata 3: 400 Bad Request - "locationId required"
**Sebep**: locationId payload'a eklenmiş (eski format)
**Çözüm**: ✅ locationId'yi payload'dan çıkardık

### Hata 4: 422 Unprocessable Entity
**Sebep**: Payload format hatalı (testMode/liveMode yerine test/live)
**Çözüm**: ✅ Key isimleri düzeltildi

## Değiştirilen Dosyalar

1. ✅ `app/Services/HighLevelService.php`
   - Line 277: Endpoint URL değişti
   - Line 287: POST endpoint değişti
   - Line 260-271: Payload key'leri değişti
   - Line 311: Error log endpoint referansı değişti

2. ✅ `CONFIG_IMPLEMENTATION_SUMMARY.md`
   - Config endpoint URL güncellendi
   - Warning eklendu: ⚠️ (NOT /config!)

3. ✅ `ENDPOINT_FIX_SUMMARY.md` (YENİ)
   - Bu dokümantasyon dosyası

## Commit Message Önerisi

```
fix: Update HighLevel config endpoint from /config to /connect

- Change endpoint URL to /payments/custom-provider/connect
- Update payload format: testMode/liveMode → test/live
- Remove locationId from payload (determined by token)
- Update logs to reflect correct endpoint

Fixes config creation 404 errors.
Aligns with HighLevel API documentation.
```

## Sonraki Adımlar

1. ✅ Kodu test et (paytr credentials gir)
2. ✅ Log'ları kontrol et (doğru endpoint kullanılıyor mu?)
3. ✅ HighLevel'da config'in oluştuğunu doğrula
4. ⚠️ Token type kontrolü ekle (Company → Location exchange)
5. 📝 README.md'ye endpoint bilgisini ekle

---

**Fix Date**: December 3, 2025
**Issue**: Wrong endpoint /config vs /connect
**Status**: ✅ FIXED
