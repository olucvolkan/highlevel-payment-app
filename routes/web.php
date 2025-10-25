<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\OAuthController;

Route::get('/', function () {
    return response()->json([
        'service' => 'HighLevel PayTR Integration',
        'status' => 'active',
        'version' => '1.0.0',
        'docs' => config('app.url') . '/docs',
    ]);
});

// OAuth Routes
Route::prefix('oauth')->group(function () {
    Route::get('/authorize', [OAuthController::class, 'authorize'])->name('oauth.authorize');
    Route::get('/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
    Route::get('/success', [OAuthController::class, 'success'])->name('oauth.success');
    Route::get('/error', [OAuthController::class, 'error'])->name('oauth.error');
    Route::post('/uninstall', [OAuthController::class, 'uninstall'])->name('oauth.uninstall');
});

// Payment Pages (iframes and redirects)
Route::prefix('payments')->group(function () {
    // Payment iframe page (embedded in HighLevel)
    Route::get('/page', [PaymentController::class, 'paymentPage'])->name('payments.page');
    
    // Payment result pages (redirects from PayTR)
    Route::get('/success', [PaymentController::class, 'success'])->name('payments.success');
    Route::get('/error', [PaymentController::class, 'error'])->name('payments.error');
    
    // PayTR callback (also accessible via GET for testing)
    Route::match(['GET', 'POST'], '/callback', [PaymentController::class, 'callback'])->name('payments.callback');
});

// Documentation and Admin Routes
Route::get('/docs', function () {
    return response()->json([
        'service' => 'HighLevel PayTR Integration API',
        'version' => '1.0.0',
        'endpoints' => [
            'health_check' => config('app.url') . '/api/health',
            'status' => config('app.url') . '/api/status',
            'payment_query' => config('app.url') . '/api/payments/query',
            'payment_page' => config('app.url') . '/payments/page',
            'oauth_authorize' => config('app.url') . '/oauth/authorize',
            'oauth_callback' => config('app.url') . '/oauth/callback',
            'paytr_callback' => config('app.url') . '/api/callbacks/paytr',
            'marketplace_webhook' => config('app.url') . '/api/webhooks/marketplace',
        ],
        'integration_urls' => [
            'query_url' => config('app.url') . '/api/payments/query',
            'payments_url' => config('app.url') . '/payments/page',
            'redirect_uri' => config('app.url') . '/oauth/callback',
            'webhook_url' => config('app.url') . '/api/webhooks/marketplace',
        ],
    ]);
})->name('docs');
