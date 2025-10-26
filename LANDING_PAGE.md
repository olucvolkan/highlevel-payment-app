Harika — o zaman senin Laravel Blade’de kullanacağın şekilde sade, modern ve HighLevel Marketplace yönlendirmesi olan bir landing page design guideline hazırlayalım.
Bu guideline, Razorpay tarzında, ama minimalist ve Türk yerel ödeme sistemlerine (iyzico + PayTR) uygun olacak.

⸻

🎨 1. Genel Tasarım Stili

Tema: Minimal, profesyonel, “fintech” tarzında sade
Renk Paleti:

Primary: #0052CC (HighLevel mavisine benzer)
Secondary: #0E1726 (Koyu arka plan metin kontrastı için)
Accent: #00C2A8 (CTA – “yükle” butonları için)
Background: #F8FAFC
Text Dark: #1E293B
Text Light: #64748B

Fontlar:
	•	Başlıklar → Inter veya Poppins, font-bold
	•	Paragraflar → Inter, font-normal
	•	Boyutlar:
	•	H1: 2.25rem (36px)
	•	H2: 1.5rem (24px)
	•	Body: 1rem (16px)
	•	Small: 0.875rem (14px)

⸻

🧩 2. Sayfa Yapısı (Blade Bölümleri)

@extends('layouts.app')

@section('content')
    @include('sections.hero')
    @include('sections.providers')
    @include('sections.pricing')
    @include('sections.cta')
@endsection


⸻

🏁 3. Hero Bölümü (sections/hero.blade.php)

Amaç: Kullanıcının hemen uygulamayı anlaması + Marketplace yönlendirmesi

<section class="text-center py-20 bg-white">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">
        Yerel Ödemeleri <span class="text-blue-600">HighLevel</span> İçinde Kolayca Yönetin
    </h1>
    <p class="text-gray-600 max-w-2xl mx-auto mb-8">
        Iyzico ve PayTR ile güvenli, hızlı ve şeffaf ödeme deneyimi. Tek entegrasyonla tüm Türkiye'ye satış yapın.
    </p>
    <a href="https://marketplace.gohighlevel.com/your-app"
       class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-3 rounded-lg text-lg font-medium shadow">
        HighLevel Marketplace’te Gör
    </a>
</section>


⸻

💳 4. Provider Bölümü (sections/providers.blade.php)

Amaç: Desteklenen sağlayıcıları logolarla sade biçimde göstermek.

<section class="py-16 bg-gray-50">
    <div class="text-center mb-10">
        <h2 class="text-2xl font-semibold text-gray-800">Desteklenen Sağlayıcılar</h2>
        <p class="text-gray-500 mt-2">Güvenilir yerel altyapılarla çalışır</p>
    </div>

    <div class="flex justify-center gap-12">
        <div class="text-center">
            <img src="/images/iyzico-logo.svg" alt="iyzico" class="h-12 mx-auto mb-4">
            <p class="text-gray-600 max-w-xs">
                PCI DSS sertifikalı, taksitli ödeme desteğiyle güvenli altyapı.
            </p>
        </div>

        <div class="text-center">
            <img src="/images/paytr-logo.svg" alt="PayTR" class="h-12 mx-auto mb-4">
            <p class="text-gray-600 max-w-xs">
                Hızlı aktivasyon ve gizli ücret olmadan işlem başına komisyon modeli.
            </p>
        </div>
    </div>
</section>


⸻

💰 5. Fiyatlandırma Bölümü (sections/pricing.blade.php)

Amaç: Basit plan sunumu (Freemium veya tek plan yeterli)

<section class="py-20 bg-white text-center">
    <h2 class="text-3xl font-semibold text-gray-900 mb-10">Fiyatlandırma</h2>

    <div class="flex justify-center">
        <div class="bg-gray-100 rounded-2xl shadow p-8 w-80">
            <h3 class="text-xl font-bold mb-4">Standart Plan</h3>
            <p class="text-gray-600 mb-6">Tüm özellikleriyle kullanım, aylık sabit ücret</p>
            <div class="text-4xl font-extrabold mb-6">₺199<span class="text-lg text-gray-500">/ay</span></div>
            <ul class="text-gray-600 mb-8 space-y-2">
                <li>✔ Iyzico & PayTR entegrasyonu</li>
                <li>✔ Hızlı kurulum (5 dakika)</li>
                <li>✔ 7/24 destek</li>
            </ul>
            <a href="https://marketplace.gohighlevel.com/your-app"
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                Marketplace’ten Yükle
            </a>
        </div>
    </div>
</section>


⸻

🚀 6. CTA (Kapanış) Bölümü (sections/cta.blade.php)

Amaç: Son bir kez aksiyona yönlendirme

<section class="bg-blue-600 text-white text-center py-16">
    <h2 class="text-3xl font-bold mb-4">HighLevel’de Ödemelerinizi Basitleştirin</h2>
    <p class="text-white/90 mb-8">Yerel altyapılarla güvenli, kolay ve şeffaf tahsilat için hemen başlayın.</p>
    <a href="https://marketplace.gohighlevel.com/your-app"
       class="bg-white text-blue-600 hover:bg-gray-100 font-medium px-6 py-3 rounded-lg">
        Uygulamayı Yükle
    </a>
</section>


⸻

📱 7. Responsive Kurallar

/* Tailwind zaten responsive ama ek öneriler */
@media (max-width: 768px) {
  .flex { flex-direction: column; align-items: center; }
  img { height: 40px; }
  section { padding: 3rem 1rem; }
}


⸻

⚡ 8. Bonus – Genel Stil (app.blade.php layout)

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yerel Ödeme Sistemi | HighLevel App</title>
    @vite('resources/css/app.css')
</head>
<body class="antialiased bg-gray-50 text-gray-800">
    @yield('content')
</body>
</html>


⸻

✅ 9. Ek Notlar
	•	Görseller: /public/images/iyzico-logo.svg, /public/images/paytr-logo.svg
	•	CTA linkleri: Marketplace URL’niz değişince güncellenecek.
	•	SEO meta: meta name="description" content="Iyzico ve PayTR destekli HighLevel yerel ödeme entegrasyonu">

⸻