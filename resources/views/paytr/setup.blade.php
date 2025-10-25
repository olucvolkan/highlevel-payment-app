<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayTR Kurulumu - HighLevel Integration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="flex items-center justify-center mb-4">
                <img src="https://www.paytr.com/images/logo.png" alt="PayTR" class="h-12 mr-4">
                <span class="text-2xl font-bold text-gray-800">×</span>
                <span class="text-2xl font-bold text-blue-600 ml-4">HighLevel</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">PayTR Hesap Kurulumu</h1>
            <p class="text-gray-600">HighLevel'da PayTR ile ödeme almak için PayTR hesap bilgilerinizi girin</p>
        </div>

        <!-- Status Cards -->
        <div class="mb-6">
            @if($isConfigured)
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="text-green-800 font-semibold">PayTR Yapılandırıldı</h3>
                            <p class="text-green-700 text-sm">PayTR hesabınız başarıyla bağlandı ve ödeme alabilirsiniz.</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-yellow-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="text-yellow-800 font-semibold">PayTR Yapılandırma Gerekli</h3>
                            <p class="text-yellow-700 text-sm">Ödeme alabilmek için PayTR hesap bilgilerinizi girmeniz gerekiyor.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Error Messages -->
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Setup Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form id="paytr-setup-form">
                <input type="hidden" name="location_id" value="{{ $locationId }}">

                <!-- Test Mode Toggle -->
                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="test_mode" id="test_mode" 
                               class="form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300" 
                               {{ $account->paytr_test_mode ? 'checked' : '' }}>
                        <span class="ml-3 text-gray-700">
                            <strong>Test Modu</strong>
                            <span class="block text-sm text-gray-500">Gerçek ödeme alınmaz, sadece test amaçlıdır</span>
                        </span>
                    </label>
                </div>

                <!-- Merchant ID -->
                <div class="mb-6">
                    <label for="merchant_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Merchant ID <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="merchant_id" 
                           id="merchant_id" 
                           value="{{ $account->paytr_merchant_id }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           placeholder="PayTR Merchant ID'nizi girin"
                           required>
                    <p class="mt-1 text-sm text-gray-500">PayTR yönetim panelinden alabilirsiniz</p>
                </div>

                <!-- Merchant Key -->
                <div class="mb-6">
                    <label for="merchant_key" class="block text-sm font-medium text-gray-700 mb-2">
                        Merchant Key <span class="text-red-500">*</span>
                    </label>
                    <input type="password" 
                           name="merchant_key" 
                           id="merchant_key" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Merchant Key'inizi girin"
                           required>
                    <p class="mt-1 text-sm text-gray-500">Bu bilgi güvenli şekilde şifrelenerek saklanır</p>
                </div>

                <!-- Merchant Salt -->
                <div class="mb-6">
                    <label for="merchant_salt" class="block text-sm font-medium text-gray-700 mb-2">
                        Merchant Salt <span class="text-red-500">*</span>
                    </label>
                    <input type="password" 
                           name="merchant_salt" 
                           id="merchant_salt" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Merchant Salt'ınızı girin"
                           required>
                    <p class="mt-1 text-sm text-gray-500">Bu bilgi güvenli şekilde şifrelenerek saklanır</p>
                </div>

                <!-- Help Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="text-blue-800 font-semibold mb-2">PayTR Bilgilerinizi Nerede Bulabilirsiniz?</h3>
                    <ol class="text-blue-700 text-sm space-y-1 list-decimal list-inside">
                        <li><a href="https://www.paytr.com" target="_blank" class="underline">PayTR.com</a>'a giriş yapın</li>
                        <li>"Ayarlar" > "API Bilgileri" bölümüne gidin</li>
                        <li>Merchant ID, Merchant Key ve Merchant Salt bilgilerini kopyalayın</li>
                        <li>Test modunu aktif etmek için test API bilgilerinizi kullanın</li>
                    </ol>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-4">
                    <button type="button" 
                            id="test-btn"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Bağlantıyı Test Et
                    </button>
                    
                    <button type="submit" 
                            id="save-btn"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Kaydet ve Yapılandır
                    </button>
                </div>

                @if($isConfigured)
                    <button type="button" 
                            id="remove-btn"
                            class="mt-3 w-full px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        PayTR Yapılandırmasını Kaldır
                    </button>
                @endif
            </form>
        </div>

        <!-- Info Section -->
        <div class="mt-8 bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Önemli Bilgiler</h3>
            <ul class="text-gray-700 space-y-2 text-sm">
                <li class="flex items-start">
                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    PayTR bilgileriniz güvenli şekilde şifrelenerek saklanır
                </li>
                <li class="flex items-start">
                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Test modunda gerçek ödeme alınmaz, sadece test amaçlıdır
                </li>
                <li class="flex items-start">
                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Kurulumdan sonra HighLevel'da ödeme almaya başlayabilirsiniz
                </li>
                <li class="flex items-start">
                    <svg class="w-4 h-4 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Ayarlarınızı istediğiniz zaman güncelleyebilirsiniz
                </li>
            </ul>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="success-modal" class="fixed inset-0 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>
            <div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-sm sm:w-full sm:p-6">
                <div class="text-center">
                    <svg class="w-12 h-12 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">Başarılı!</h3>
                    <p class="mt-1 text-sm text-gray-500">PayTR hesabınız başarıyla yapılandırıldı.</p>
                </div>
                <div class="mt-5">
                    <button type="button" onclick="window.location.reload()" class="w-full px-4 py-2 text-white bg-green-600 rounded-md hover:bg-green-700">
                        Tamam
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Test credentials
        document.getElementById('test-btn').addEventListener('click', async function() {
            const btn = this;
            const formData = new FormData(document.getElementById('paytr-setup-form'));
            
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 inline mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Test Ediliyor...';
            
            try {
                const response = await fetch('/paytr/test', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(Object.fromEntries(formData))
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'PayTR bağlantısı başarılı! Bilgileriniz doğru.');
                } else {
                    showAlert('error', 'Bağlantı başarısız: ' + (result.error || 'Bilinmeyen hata'));
                }
            } catch (error) {
                showAlert('error', 'Test sırasında hata oluştu: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Bağlantıyı Test Et';
            }
        });
        
        // Save credentials
        document.getElementById('paytr-setup-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('save-btn');
            const formData = new FormData(this);
            
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 inline mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Kaydediliyor...';
            
            try {
                const response = await fetch('/paytr/credentials', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(Object.fromEntries(formData))
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('success-modal').classList.remove('hidden');
                } else {
                    showAlert('error', 'Kaydetme başarısız: ' + (result.message || 'Bilinmeyen hata'));
                }
            } catch (error) {
                showAlert('error', 'Kaydetme sırasında hata oluştu: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Kaydet ve Yapılandır';
            }
        });
        
        // Remove configuration
        @if($isConfigured)
        document.getElementById('remove-btn').addEventListener('click', async function() {
            if (!confirm('PayTR yapılandırmanızı kaldırmak istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
                return;
            }
            
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 inline mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Kaldırılıyor...';
            
            try {
                const response = await fetch('/paytr/config?location_id={{ $locationId }}', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.reload();
                } else {
                    showAlert('error', 'Kaldırma başarısız: ' + (result.message || 'Bilinmeyen hata'));
                }
            } catch (error) {
                showAlert('error', 'Kaldırma sırasında hata oluştu: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>PayTR Yapılandırmasını Kaldır';
            }
        });
        @endif
        
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
            const textColor = type === 'success' ? 'text-green-800' : 'text-red-800';
            const iconColor = type === 'success' ? 'text-green-500' : 'text-red-500';
            const icon = type === 'success' ? 
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>' :
                '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>';
            
            alertDiv.className = `${bgColor} border rounded-lg p-4 mb-6`;
            alertDiv.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 ${iconColor} mr-3" fill="currentColor" viewBox="0 0 20 20">
                        ${icon}
                    </svg>
                    <p class="${textColor}">${message}</p>
                </div>
            `;
            
            // Insert at the top of the form
            const form = document.getElementById('paytr-setup-form');
            form.parentNode.insertBefore(alertDiv, form);
            
            // Remove after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>