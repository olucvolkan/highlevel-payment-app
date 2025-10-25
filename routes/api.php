<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// HighLevel Payment Integration Routes
Route::prefix('payments')->group(function () {
    // Payment query endpoint (used by HighLevel for all payment operations)
    Route::post('/query', [PaymentController::class, 'query'])->name('payments.query');
    
    // Payment status check endpoint (used by iframe for polling)
    Route::post('/status', [PaymentController::class, 'status'])->name('payments.status');
});

// PayTR Callback Routes
Route::prefix('callbacks')->group(function () {
    // PayTR payment callback (POST from PayTR servers)
    Route::post('/paytr', [WebhookController::class, 'paytrCallback'])->name('callbacks.paytr');
});

// HighLevel Webhook Routes
Route::prefix('webhooks')->group(function () {
    // HighLevel marketplace webhooks (install/uninstall)
    Route::post('/marketplace', [WebhookController::class, 'marketplaceWebhook'])->name('webhooks.marketplace');
    
    // HighLevel payment webhooks
    Route::post('/highlevel', [WebhookController::class, 'highlevelPaymentWebhook'])->name('webhooks.highlevel');
});

// Health Check and Status Routes
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'HighLevel PayTR Integration',
        'timestamp' => now()->toIso8601String(),
        'version' => '1.0.0',
    ]);
})->name('health');

Route::get('/status', function () {
    return response()->json([
        'status' => 'active',
        'providers' => [
            'paytr' => [
                'enabled' => !empty(config('services.paytr.merchant_id')),
                'test_mode' => config('services.paytr.test_mode', false),
            ],
        ],
        'highlevel' => [
            'client_configured' => !empty(config('services.highlevel.client_id')),
        ],
        'database' => [
            'connected' => \Illuminate\Support\Facades\DB::connection()->getPdo() ? true : false,
        ],
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('status');