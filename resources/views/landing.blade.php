@extends('layouts.landing')

@section('content')

<!-- Hero Section -->
<section class="bg-white py-20 px-4">
    <div class="max-w-4xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6 leading-tight">
            Yerel Ödemeleri <span class="text-primary">HighLevel</span> İçinde<br>Kolayca Yönetin
        </h1>
        <p class="text-lg md:text-xl text-gray-600 max-w-2xl mx-auto mb-8 leading-relaxed">
            PayTR ve iyzico ile güvenli, hızlı ve şeffaf ödeme deneyimi.<br>
            Tek entegrasyonla tüm Türkiye'ye satış yapın.
        </p>
        <a href="https://marketplace.gohighlevel.com/your-app"
           target="_blank"
           class="inline-block bg-accent hover:bg-accent/90 text-white px-8 py-4 rounded-lg text-lg font-semibold shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
            HighLevel Marketplace'te Gör →
        </a>
    </div>
</section>

<!-- Providers Section -->
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-semibold text-gray-900 mb-3">Desteklenen Sağlayıcılar</h2>
            <p class="text-gray-600 text-lg">Güvenilir yerel altyapılarla çalışır</p>
        </div>

        <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <!-- PayTR Card -->
            <div class="bg-white rounded-xl shadow-md p-8 text-center hover:shadow-lg transition-shadow">
                <div class="h-16 mb-6 flex items-center justify-center">
                    <!-- Logo Placeholder - Sen sonra ekleyeceksin -->
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-3 rounded-lg text-xl font-bold">
                        PayTR
                    </div>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-3">PayTR</h3>
                <p class="text-gray-600 leading-relaxed">
                    Hızlı aktivasyon ve gizli ücret olmadan işlem başına komisyon modeli.
                    Türkiye'nin önde gelen ödeme altyapısı.
                </p>
            </div>

            <!-- iyzico Card -->
            <div class="bg-white rounded-xl shadow-md p-8 text-center hover:shadow-lg transition-shadow">
                <div class="h-16 mb-6 flex items-center justify-center">
                    <!-- Logo Placeholder - Sen sonra ekleyeceksin -->
                    <div class="bg-gradient-to-r from-orange-500 to-red-600 text-white px-6 py-3 rounded-lg text-xl font-bold">
                        iyzico
                    </div>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-3">iyzico</h3>
                <p class="text-gray-600 leading-relaxed">
                    PCI DSS sertifikalı, taksitli ödeme desteğiyle güvenli altyapı.
                    Her ölçekte işletme için ideal çözüm.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-20 px-4 bg-white">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-semibold text-gray-900 mb-3">Neden Bu Entegrasyon?</h2>
            <p class="text-gray-600 text-lg">HighLevel'da yerel ödeme almak hiç bu kadar kolay olmamıştı</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-accent/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Hızlı Kurulum</h3>
                <p class="text-gray-600">5 dakikada aktif. Karmaşık entegrasyon süreci yok, hemen kullanmaya başlayın.</p>
            </div>

            <!-- Feature 2 -->
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-accent/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Güvenli Altyapı</h3>
                <p class="text-gray-600">Tüm ödemeler şifreli ve güvenli. PCI DSS uyumlu altyapı ile huzurla satış yapın.</p>
            </div>

            <!-- Feature 3 -->
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-accent/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">7/24 Destek</h3>
                <p class="text-gray-600">Her zaman yanınızdayız. Sorunlarınızı anında çözüme kavuşturuyoruz.</p>
            </div>

            <!-- Feature 4 -->
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Taksit Desteği</h3>
                <p class="text-gray-600">Müşterileriniz taksitle ödeme yapabilir. Satışlarınızı artırın.</p>
            </div>

            <!-- Feature 5 -->
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Detaylı Raporlar</h3>
                <p class="text-gray-600">Tüm ödemelerinizi HighLevel içinde takip edin. Şeffaf raporlama.</p>
            </div>

            <!-- Feature 6 -->
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Şeffaf Fiyatlandırma</h3>
                <p class="text-gray-600">Gizli ücret yok. Ne ödediğinizi tam olarak biliyorsunuz.</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-20 px-4 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-semibold text-gray-900 mb-3">Nasıl Çalışır?</h2>
            <p class="text-gray-600 text-lg">3 basit adımda ödeme almaya başlayın</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <!-- Step 1 -->
            <div class="relative">
                <div class="bg-white rounded-xl p-8 shadow-md">
                    <div class="absolute -top-4 left-8 w-12 h-12 bg-accent text-white rounded-full flex items-center justify-center text-xl font-bold shadow-lg">
                        1
                    </div>
                    <div class="mt-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Marketplace'ten Yükleyin</h3>
                        <p class="text-gray-600">HighLevel Marketplace'ten uygulamayı tek tıkla hesabınıza ekleyin.</p>
                    </div>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="relative">
                <div class="bg-white rounded-xl p-8 shadow-md">
                    <div class="absolute -top-4 left-8 w-12 h-12 bg-accent text-white rounded-full flex items-center justify-center text-xl font-bold shadow-lg">
                        2
                    </div>
                    <div class="mt-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Ayarlarınızı Yapın</h3>
                        <p class="text-gray-600">PayTR veya iyzico hesap bilgilerinizi girin. Hazırsınız!</p>
                    </div>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="relative">
                <div class="bg-white rounded-xl p-8 shadow-md">
                    <div class="absolute -top-4 left-8 w-12 h-12 bg-accent text-white rounded-full flex items-center justify-center text-xl font-bold shadow-lg">
                        3
                    </div>
                    <div class="mt-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">Ödeme Almaya Başlayın</h3>
                        <p class="text-gray-600">Müşterileriniz artık güvenle ödeme yapabilir. Siz de para kazanın!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="bg-gradient-to-r from-primary to-blue-700 text-white py-20 px-4">
    <div class="max-w-4xl mx-auto text-center">
        <h2 class="text-3xl md:text-4xl font-bold mb-6">
            HighLevel'de Ödemelerinizi Basitleştirin
        </h2>
        <p class="text-xl text-white/90 mb-10 leading-relaxed max-w-2xl mx-auto">
            Yerel altyapılarla güvenli, kolay ve şeffaf tahsilat için hemen başlayın.
            Binlerce işletme güveniyor.
        </p>
        <a href="https://marketplace.gohighlevel.com/your-app"
           target="_blank"
           class="inline-block bg-white text-primary hover:bg-gray-100 font-semibold px-10 py-4 rounded-lg text-lg shadow-xl hover:shadow-2xl transition-all duration-200 transform hover:-translate-y-0.5">
            Uygulamayı Şimdi Yükle →
        </a>
        <p class="mt-6 text-white/80 text-sm">
            ✓ Kredi kartı gerekmez &nbsp;&nbsp;|&nbsp;&nbsp; ✓ 5 dakikada kurulum &nbsp;&nbsp;|&nbsp;&nbsp; ✓ 7/24 destek
        </p>
    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-400 py-12 px-4">
    <div class="max-w-6xl mx-auto text-center">
        <p class="text-lg mb-4">
            <span class="text-white font-semibold">PayTR HighLevel Integration</span>
        </p>
        <p class="text-sm">
            Türkiye'nin yerel ödeme altyapıları ile HighLevel CRM entegrasyonu
        </p>
        <div class="mt-6 text-xs text-gray-500">
            <p>© {{ date('Y') }} PayTR HighLevel Integration. Tüm hakları saklıdır.</p>
        </div>
    </div>
</footer>

@endsection
