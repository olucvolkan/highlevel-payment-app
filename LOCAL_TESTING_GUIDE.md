# LOCAL TEST REHBERİ - HIGHLEVEL ENTEGRASYONU

> **Amaç:** HighLevel içinde açılacak sayfaları local environment'ta nasıl test edeceğinizi açıklamak

---

## İÇİNDEKİLER

1. [Hızlı Başlangıç](#hızlı-başlangıç)
2. [ngrok ile Public URL Oluşturma](#ngrok-ile-public-url-oluşturma)
3. [Test Senaryoları](#test-senaryoları)
4. [HighLevel Custom Page Simülasyonu](#highlevel-custom-page-simülasyonu)
5. [Debugging ve Developer Tools](#debugging-ve-developer-tools)

---

## HIZLI BAŞLANGIÇ

### Gerekli Araçlar

1. **ngrok** - Local'i public URL'e dönüştürür
2. **Laravel development server**
3. **Test database** (Supabase veya local PostgreSQL)

---

## NGROK İLE PUBLIC URL OLUŞTURMA

### 1. ngrok Kurulumu

**macOS (Homebrew):**
```bash
brew install ngrok
```

**Manuel Kurulum:**
```bash
# Download
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-darwin-amd64.zip

# Unzip
unzip ngrok-v3-stable-darwin-amd64.zip

# Move to PATH
sudo mv ngrok /usr/local/bin/
```

**Doğrulama:**
```bash
ngrok version
# ngrok version 3.x.x
```

### 2. ngrok Account (Opsiyonel ama Önerilen)

1. **Signup:** https://ngrok.com/signup
2. **Auth Token Al:**
   ```bash
   ngrok config add-authtoken YOUR_AUTH_TOKEN
   ```
3. **Avantajları:**
   - Daha uzun session süresi
   - Custom subdomain (ücretli plan)
   - Daha fazla connection

### 3. Laravel Server + ngrok Başlatma

**Terminal 1 - Laravel:**
```bash
cd /Users/volkanoluc/Projects/highlevel-paytr-integration

# Laravel server başlat
php artisan serve --port=8000
```

**Terminal 2 - ngrok:**
```bash
# Port 8000'i public'e aç
ngrok http 8000
```

**ngrok Output:**
```
ngrok

Session Status                online
Account                       your@email.com
Version                       3.x.x
Region                        United States (us)
Latency                       45ms
Web Interface                 http://127.0.0.1:4040
Forwarding                    https://a1b2c3d4.ngrok.io -> http://localhost:8000

Connections                   ttl     opn     rt1     rt5     p50     p90
                              0       0       0.00    0.00    0.00    0.00
```

**Public URL'iniz:** `https://a1b2c3d4.ngrok.io` (her çalıştırmada değişir)

### 4. .env Dosyasını Güncelle

```bash
# .env
APP_URL=https://a1b2c3d4.ngrok.io

# HighLevel OAuth
HIGHLEVEL_REDIRECT_URI=https://a1b2c3d4.ngrok.io/oauth/callback
```

**Cache temizle:**
```bash
php artisan config:clear
php artisan cache:clear
```

### 5. Test Edilebilir URL'ler

Artık şu URL'lere browser'dan veya API client'tan erişebilirsiniz:

```
✅ https://a1b2c3d4.ngrok.io/
✅ https://a1b2c3d4.ngrok.io/paytr/setup?location_id=test_loc_123
✅ https://a1b2c3d4.ngrok.io/payments/page?locationId=test_loc_123&amount=10000
✅ https://a1b2c3d4.ngrok.io/api/payments/query
```

---

## TEST SENARYOLARI

### TEST 1: PayTR Setup Sayfası

#### 1.1 Doğrudan Test (Browser'da)

```bash
# URL
https://a1b2c3d4.ngrok.io/paytr/setup?location_id=test_loc_123
```

**Adımlar:**
1. Browser'da URL'i aç
2. Form görünmeli:
   - Merchant ID input
   - Merchant Key input (password)
   - Merchant Salt input (password)
   - Test Mode checkbox
   - "Test Credentials" button
   - "Save Configuration" button

3. Test credentials gir:
   ```
   Merchant ID: test_merchant
   Merchant Key: test_key_123
   Merchant Salt: test_salt_456
   Test Mode: ✓ (checked)
   ```

4. "Test Credentials" butonuna tıkla
   - Loading spinner görünmeli
   - 2-3 saniye sonra success/error mesajı

5. "Save Configuration" butonuna tıkla
   - Success alert görünmeli
   - Sayfa yenilenince "Current Configuration" bölümü görünmeli

#### 1.2 Database Kontrolü

```bash
# Supabase veya local PostgreSQL
psql -h your_supabase_host -U postgres -d highlevel_payments

# Account var mı kontrol et
SELECT * FROM hl_accounts WHERE location_id = 'test_loc_123';

# PayTR credentials kaydedildi mi?
SELECT
  location_id,
  paytr_merchant_id,
  paytr_configured,
  paytr_test_mode,
  created_at
FROM hl_accounts
WHERE location_id = 'test_loc_123';
```

#### 1.3 Network Tab İnceleme

**Browser DevTools → Network Tab:**

1. **Test Credentials Request:**
   ```
   POST https://a1b2c3d4.ngrok.io/paytr/test

   Request Body:
   {
     "merchant_id": "test_merchant",
     "merchant_key": "test_key_123",
     "merchant_salt": "test_salt_456",
     "test_mode": true,
     "location_id": "test_loc_123"
   }

   Response (Success):
   {
     "success": true,
     "message": "Credentials are valid"
   }

   Response (Error):
   {
     "success": false,
     "message": "Invalid credentials",
     "details": "Hash mismatch"
   }
   ```

2. **Save Credentials Request:**
   ```
   POST https://a1b2c3d4.ngrok.io/paytr/credentials

   Response:
   {
     "success": true,
     "message": "Configuration saved successfully"
   }
   ```

---

### TEST 2: Payment iframe Sayfası

#### 2.1 Önce Test Account Oluştur

```bash
# Laravel Tinker
php artisan tinker
```

```php
// Test account oluştur
$account = \App\Models\HLAccount::create([
    'location_id' => 'test_loc_123',
    'company_id' => 'test_company_456',
    'user_id' => 'test_user_789',
    'access_token' => 'test_token_' . Str::random(40),
    'refresh_token' => 'test_refresh_' . Str::random(40),
    'token_expires_at' => now()->addDays(30),
    'scopes' => ['payments/orders.write', 'payments/custom-provider.write'],
    'paytr_merchant_id' => 'test_merchant',
    'paytr_merchant_key' => encrypt('test_key_123'),
    'paytr_merchant_salt' => encrypt('test_salt_456'),
    'paytr_test_mode' => true,
    'paytr_configured' => true,
]);

echo "Account created with ID: " . $account->id . "\n";
exit;
```

#### 2.2 Payment Page Test

**URL:**
```
https://a1b2c3d4.ngrok.io/payments/page?locationId=test_loc_123&transactionId=txn_test_001&amount=10000&currency=TRY&email=test@example.com&contactId=cont_123
```

**Query Parameters Açıklaması:**
- `locationId`: test_loc_123 (az önce oluşturduğumuz account)
- `transactionId`: Unique ID (HighLevel'dan gelir)
- `amount`: 10000 (100.00 TRY - kuruş cinsinden)
- `currency`: TRY
- `email`: Müşteri email
- `contactId`: HighLevel contact ID

**Beklenen Görünüm:**
1. Loading spinner (2-3 saniye)
2. PayTR iframe yüklenir
3. Ödeme formu görünür

**Console'da Görmek İstediğiniz:**
```javascript
// postMessage gönderildi
{
  type: 'custom_provider_ready',
  data: {
    merchantOid: 'ORDER_1234567890_5678',
    transactionId: 'txn_test_001'
  }
}
```

#### 2.3 iframe Simülasyon Test

**Test HTML Oluştur:**

`test-iframe.html` dosyası oluştur:
```html
<!DOCTYPE html>
<html>
<head>
    <title>HighLevel iframe Test</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        iframe {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .messages {
            margin-top: 20px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        .message {
            padding: 5px;
            margin: 2px 0;
            background: white;
            border-left: 3px solid #4CAF50;
        }
        .message.error {
            border-left-color: #f44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Payment iframe Test</h1>
        <p>HighLevel içinde böyle görünecek:</p>

        <iframe id="paymentFrame" src=""></iframe>

        <div class="messages" id="messages">
            <strong>postMessage Events:</strong>
        </div>
    </div>

    <script>
        // ngrok URL'inizi buraya yazın
        const NGROK_URL = 'https://a1b2c3d4.ngrok.io';

        // iframe URL oluştur
        const params = new URLSearchParams({
            locationId: 'test_loc_123',
            transactionId: 'txn_test_001',
            amount: '10000',
            currency: 'TRY',
            email: 'test@example.com',
            contactId: 'cont_123'
        });

        const iframeUrl = `${NGROK_URL}/payments/page?${params.toString()}`;
        document.getElementById('paymentFrame').src = iframeUrl;

        // postMessage listener
        window.addEventListener('message', function(event) {
            console.log('Received postMessage:', event.data);

            const messagesDiv = document.getElementById('messages');
            const messageEl = document.createElement('div');
            messageEl.className = 'message';

            if (event.data.type) {
                messageEl.innerHTML = `
                    <strong>${event.data.type}</strong><br>
                    ${JSON.stringify(event.data.data || event.data, null, 2)}
                `;

                // Error message için farklı stil
                if (event.data.type.includes('error')) {
                    messageEl.classList.add('error');
                }

                messagesDiv.appendChild(messageEl);
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        });

        console.log('iframe URL:', iframeUrl);
    </script>
</body>
</html>
```

**Kullanım:**
1. `test-iframe.html` dosyasını browser'da aç
2. ngrok URL'inizi `NGROK_URL` değişkenine yazın
3. Sayfayı yenileyin
4. iframe yüklenir ve postMessage events görürsünüz

**Beklenen Events:**
```javascript
// 1. iframe yüklendiğinde
{
  type: 'custom_provider_ready',
  data: {
    merchantOid: 'ORDER_xxx',
    transactionId: 'txn_test_001'
  }
}

// 2. Ödeme başarılı olduğunda
{
  type: 'custom_element_success_response',
  data: {
    chargeId: 'chrg_xxx',
    transactionId: 'txn_test_001',
    amount: 10000,
    currency: 'TRY'
  }
}

// 3. Ödeme başarısız olduğunda
{
  type: 'custom_element_error_response',
  data: {
    error: 'Payment failed',
    transactionId: 'txn_test_001'
  }
}
```

---

### TEST 3: Payment Query Endpoint

#### 3.1 cURL ile Test

**Verify Payment:**
```bash
curl -X POST https://a1b2c3d4.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "verify",
    "locationId": "test_loc_123",
    "transactionId": "txn_test_001",
    "chargeId": "chrg_xxx"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "failed": false,
  "chargeId": "chrg_xxx",
  "transactionId": "txn_test_001",
  "amount": 10000,
  "currency": "TRY",
  "status": "success"
}
```

**List Payment Methods:**
```bash
curl -X POST https://a1b2c3d4.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{
    "type": "list_payment_methods",
    "locationId": "test_loc_123",
    "contactId": "cont_123"
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

#### 3.2 Postman Collection

**Import to Postman:**

`postman_collection.json`:
```json
{
  "info": {
    "name": "HighLevel PayTR Integration",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Payment Query - Verify",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"type\": \"verify\",\n  \"locationId\": \"test_loc_123\",\n  \"transactionId\": \"txn_test_001\",\n  \"chargeId\": \"chrg_xxx\"\n}"
        },
        "url": {
          "raw": "{{BASE_URL}}/api/payments/query",
          "host": ["{{BASE_URL}}"],
          "path": ["api", "payments", "query"]
        }
      }
    },
    {
      "name": "Payment Query - List Methods",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"type\": \"list_payment_methods\",\n  \"locationId\": \"test_loc_123\",\n  \"contactId\": \"cont_123\"\n}"
        },
        "url": {
          "raw": "{{BASE_URL}}/api/payments/query",
          "host": ["{{BASE_URL}}"],
          "path": ["api", "payments", "query"]
        }
      }
    },
    {
      "name": "PayTR Setup - Test Credentials",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "X-CSRF-TOKEN",
            "value": "{{CSRF_TOKEN}}"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"merchant_id\": \"test_merchant\",\n  \"merchant_key\": \"test_key_123\",\n  \"merchant_salt\": \"test_salt_456\",\n  \"test_mode\": true,\n  \"location_id\": \"test_loc_123\"\n}"
        },
        "url": {
          "raw": "{{BASE_URL}}/paytr/test",
          "host": ["{{BASE_URL}}"],
          "path": ["paytr", "test"]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "BASE_URL",
      "value": "https://a1b2c3d4.ngrok.io"
    },
    {
      "key": "CSRF_TOKEN",
      "value": "your_csrf_token"
    }
  ]
}
```

---

## HIGHLEVEL CUSTOM PAGE SIMÜLASYONU

### Senaryo: HighLevel içinde Custom Page

HighLevel'da app install ettikten sonra kullanıcılar "Settings → Integrations → PayTR" gibi bir sayfaya gidecekler. Bu sayfa bizim PayTR Setup sayfamızı iframe içinde gösterecek.

### Local Simülasyon

**1. Custom Page Simülatörü Oluştur:**

`highlevel-simulator.html`:
```html
<!DOCTYPE html>
<html>
<head>
    <title>HighLevel Custom Page Simulator</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
        }

        /* HighLevel navbar simulation */
        .hl-navbar {
            background: #1a1f36;
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .hl-logo {
            font-size: 20px;
            font-weight: bold;
            color: #3b82f6;
        }

        .hl-nav-items {
            margin-left: auto;
            display: flex;
            gap: 20px;
        }

        .hl-nav-item {
            color: #9ca3af;
            text-decoration: none;
            font-size: 14px;
        }

        /* HighLevel sidebar simulation */
        .hl-container {
            display: flex;
            height: calc(100vh - 60px);
        }

        .hl-sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #e5e7eb;
            padding: 20px;
        }

        .hl-sidebar-item {
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: #4b5563;
        }

        .hl-sidebar-item:hover {
            background: #f3f4f6;
        }

        .hl-sidebar-item.active {
            background: #3b82f6;
            color: white;
        }

        /* Main content area */
        .hl-content {
            flex: 1;
            padding: 20px;
            overflow: auto;
        }

        .hl-page-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .hl-page-title {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .hl-page-desc {
            font-size: 14px;
            color: #6b7280;
        }

        /* iframe container */
        .hl-iframe-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 0;
            overflow: hidden;
        }

        #appFrame {
            width: 100%;
            height: calc(100vh - 200px);
            border: none;
        }

        /* Debug panel */
        .debug-panel {
            position: fixed;
            bottom: 0;
            right: 0;
            width: 400px;
            max-height: 300px;
            background: #1f2937;
            color: #f9fafb;
            padding: 15px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            border-top-left-radius: 8px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
        }

        .debug-title {
            font-weight: bold;
            color: #60a5fa;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .debug-message {
            padding: 5px;
            margin: 3px 0;
            background: #374151;
            border-radius: 3px;
            border-left: 3px solid #10b981;
        }

        .debug-message.error {
            border-left-color: #ef4444;
        }

        .debug-message.info {
            border-left-color: #3b82f6;
        }
    </style>
</head>
<body>
    <!-- HighLevel Navbar -->
    <div class="hl-navbar">
        <div class="hl-logo">⚡ HighLevel</div>
        <div class="hl-nav-items">
            <a href="#" class="hl-nav-item">Dashboard</a>
            <a href="#" class="hl-nav-item">Contacts</a>
            <a href="#" class="hl-nav-item">Settings</a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="hl-container">
        <!-- Sidebar -->
        <div class="hl-sidebar">
            <div class="hl-sidebar-item">General</div>
            <div class="hl-sidebar-item">Business Profile</div>
            <div class="hl-sidebar-item">Phone Numbers</div>
            <div class="hl-sidebar-item active">Integrations</div>
            <div class="hl-sidebar-item">Payment Providers</div>
        </div>

        <!-- Content -->
        <div class="hl-content">
            <!-- Page Header -->
            <div class="hl-page-header">
                <div class="hl-page-title">PayTR Payment Gateway</div>
                <div class="hl-page-desc">Configure your PayTR merchant credentials for payment processing</div>
            </div>

            <!-- App iframe -->
            <div class="hl-iframe-container">
                <iframe id="appFrame" src=""></iframe>
            </div>
        </div>
    </div>

    <!-- Debug Panel -->
    <div class="debug-panel">
        <div class="debug-title">📡 postMessage Events</div>
        <div id="debugMessages"></div>
    </div>

    <script>
        // 🔧 BURAYA NGROK URL'İNİZİ YAZIN
        const NGROK_URL = 'https://a1b2c3d4.ngrok.io';

        // Test location ID
        const TEST_LOCATION_ID = 'test_loc_123';

        // iframe URL oluştur
        const iframeUrl = `${NGROK_URL}/paytr/setup?location_id=${TEST_LOCATION_ID}`;

        // iframe'i yükle
        document.getElementById('appFrame').src = iframeUrl;

        // Debug logging
        function addDebugMessage(message, type = 'info') {
            const debugDiv = document.getElementById('debugMessages');
            const msgEl = document.createElement('div');
            msgEl.className = `debug-message ${type}`;
            msgEl.innerHTML = `
                <div style="color: #9ca3af; font-size: 10px;">${new Date().toLocaleTimeString()}</div>
                <div>${message}</div>
            `;
            debugDiv.insertBefore(msgEl, debugDiv.firstChild);

            // Keep only last 50 messages
            while (debugDiv.children.length > 50) {
                debugDiv.removeChild(debugDiv.lastChild);
            }
        }

        // Listen for postMessage events
        window.addEventListener('message', function(event) {
            console.log('📨 Received postMessage:', event);

            // Log to debug panel
            addDebugMessage(
                `<strong>${event.data.type || 'unknown'}</strong><br>${JSON.stringify(event.data, null, 2)}`,
                event.data.type && event.data.type.includes('error') ? 'error' : 'info'
            );

            // Handle different message types
            switch(event.data.type) {
                case 'custom_provider_ready':
                    console.log('✅ Payment provider is ready');
                    addDebugMessage('✅ Provider ready', 'info');
                    break;

                case 'custom_element_success_response':
                    console.log('✅ Payment successful:', event.data.data);
                    addDebugMessage(`✅ Payment success: ${event.data.data.chargeId}`, 'info');
                    alert('Payment successful! Charge ID: ' + event.data.data.chargeId);
                    break;

                case 'custom_element_error_response':
                    console.error('❌ Payment failed:', event.data.data);
                    addDebugMessage(`❌ Payment failed: ${event.data.data.error}`, 'error');
                    alert('Payment failed: ' + event.data.data.error);
                    break;

                case 'custom_element_close_response':
                    console.log('❌ Payment cancelled');
                    addDebugMessage('❌ Payment cancelled by user', 'error');
                    break;
            }
        });

        // Initial log
        addDebugMessage(`🚀 Loading iframe from: ${iframeUrl}`, 'info');

        console.log('HighLevel Simulator started');
        console.log('iframe URL:', iframeUrl);
    </script>
</body>
</html>
```

**2. Kullanım:**

```bash
# 1. ngrok ve Laravel'i çalıştır (önceki adımlar)

# 2. highlevel-simulator.html dosyasını oluştur

# 3. ngrok URL'inizi dosyaya yaz
# const NGROK_URL = 'https://YOUR_NGROK_URL.ngrok.io';

# 4. Browser'da aç
open highlevel-simulator.html
# veya
firefox highlevel-simulator.html
```

**3. Beklenen Görünüm:**

- ✅ HighLevel UI simülasyonu (navbar, sidebar)
- ✅ PayTR Setup sayfası iframe içinde
- ✅ Sağ altta debug panel (postMessage events)
- ✅ Form doldur → Test/Save → Success

---

## DEBUGGING VE DEVELOPER TOOLS

### 1. ngrok Web Interface

**URL:** http://127.0.0.1:4040

**Özellikler:**
- Tüm HTTP requests görüntüleme
- Request/response inspection
- Replay requests
- Status codes

**Kullanım:**
```bash
# ngrok çalışırken browser'da aç
open http://127.0.0.1:4040
```

### 2. Laravel Telescope (Opsiyonel)

**Kurulum:**
```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

**Kullanım:**
```
https://a1b2c3d4.ngrok.io/telescope
```

**Gösterir:**
- Requests
- Database queries
- Jobs
- Logs
- Exceptions

### 3. Browser DevTools

**Console Tab:**
```javascript
// postMessage dinle
window.addEventListener('message', (e) => {
  console.log('postMessage:', e.data);
});

// iframe'e mesaj gönder (test için)
const iframe = document.getElementById('paymentFrame');
iframe.contentWindow.postMessage({
  type: 'test',
  data: { foo: 'bar' }
}, '*');
```

**Network Tab:**
- XHR/Fetch requests
- Timing
- Headers
- Response

**Application Tab:**
- LocalStorage
- SessionStorage
- Cookies

### 4. Laravel Log Monitoring

**Terminal 3:**
```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log

# Payment logs
tail -f storage/logs/payments/$(date +%Y-%m-%d).log
```

**Filtered logs:**
```bash
# Sadece error'ları göster
tail -f storage/logs/laravel.log | grep ERROR

# Payment related logs
tail -f storage/logs/laravel.log | grep -i payment
```

---

## HIZLI TEST CHECKLIST

### ✅ Setup Test

```bash
# 1. ngrok başlat
ngrok http 8000

# 2. Laravel başlat
php artisan serve

# 3. Setup sayfasını test et
open https://YOUR_NGROK_URL.ngrok.io/paytr/setup?location_id=test_loc_123

# 4. Credentials kaydet ve database kontrolü
psql -h ... -c "SELECT * FROM hl_accounts WHERE location_id='test_loc_123';"
```

### ✅ Payment iframe Test

```bash
# 1. Test account oluştur (Tinker)
php artisan tinker
# [Account oluşturma kodu]

# 2. Payment page aç
open https://YOUR_NGROK_URL.ngrok.io/payments/page?locationId=test_loc_123&amount=10000...

# 3. highlevel-simulator.html ile test et
open highlevel-simulator.html
```

### ✅ API Endpoint Test

```bash
# Query endpoint
curl -X POST https://YOUR_NGROK_URL.ngrok.io/api/payments/query \
  -H "Content-Type: application/json" \
  -d '{"type": "verify", "locationId": "test_loc_123"}'
```

---

## SORUN GİDERME

### ngrok "ERR_NGROK_3200"

**Problem:** ngrok session expired

**Çözüm:**
```bash
# Yeniden başlat
ngrok http 8000

# Yeni URL'i .env'ye yaz ve cache temizle
php artisan config:clear
```

### "CSRF Token Mismatch"

**Problem:** 419 error on POST requests

**Çözüm:**
```bash
# Session sürücüsünü kontrol et
# .env
SESSION_DRIVER=file  # veya database

# Cache temizle
php artisan config:clear
php artisan cache:clear
```

### iframe "Refused to Connect"

**Problem:** X-Frame-Options blocking

**Çözüm:**
```php
// app/Http/Middleware/FrameGuard.php (oluştur)
public function handle($request, Closure $next)
{
    $response = $next($request);
    $response->headers->remove('X-Frame-Options');
    return $response;
}

// Kernel.php'de ekle
protected $middleware = [
    // ...
    \App\Http\Middleware\FrameGuard::class,
];
```

---

## SONUÇ

Bu rehberi kullanarak:
- ✅ Local environment'ı public URL'e çevirebilirsiniz
- ✅ HighLevel içindeki görünümü simüle edebilirsiniz
- ✅ PayTR Setup sayfasını test edebilirsiniz
- ✅ Payment iframe flow'unu test edebilirsiniz
- ✅ postMessage events'leri debug edebilirsiniz

**Kısa Özet:**
```bash
# Terminal 1
php artisan serve

# Terminal 2
ngrok http 8000

# Terminal 3
tail -f storage/logs/laravel.log

# Browser
open highlevel-simulator.html
```

---

*İyi testler! 🚀*
