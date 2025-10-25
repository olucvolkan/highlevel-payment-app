<?php

namespace App\Logging;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentLogger
{
    /**
     * Log payment initialized.
     */
    public function logPaymentInitialized(Payment $payment, array $result): void
    {
        Log::channel('single')->info('Payment initialized', [
            'payment_id' => $payment->id,
            'merchant_oid' => $payment->merchant_oid,
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'location_id' => $payment->location_id,
            'iframe_url' => $result['iframe_url'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log payment success.
     */
    public function logPaymentSuccess(Payment $payment, array $callbackData): void
    {
        Log::channel('single')->info('Payment successful', [
            'payment_id' => $payment->id,
            'merchant_oid' => $payment->merchant_oid,
            'transaction_id' => $payment->transaction_id,
            'charge_id' => $payment->charge_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'provider_payment_id' => $payment->provider_payment_id,
            'location_id' => $payment->location_id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'callback_data' => $callbackData,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log payment failed.
     */
    public function logPaymentFailed(Payment $payment, array $callbackData): void
    {
        Log::channel('single')->error('Payment failed', [
            'payment_id' => $payment->id,
            'merchant_oid' => $payment->merchant_oid,
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'location_id' => $payment->location_id,
            'error_message' => $payment->error_message,
            'callback_data' => $callbackData,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log refund.
     */
    public function logRefund(Payment $payment, float $amount, array $result): void
    {
        Log::channel('single')->info('Refund processed', [
            'payment_id' => $payment->id,
            'merchant_oid' => $payment->merchant_oid,
            'transaction_id' => $payment->transaction_id,
            'original_amount' => $payment->amount,
            'refund_amount' => $amount,
            'provider' => $payment->provider,
            'location_id' => $payment->location_id,
            'result' => $result,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log payment verification.
     */
    public function logVerification(string $transactionId, array $result): void
    {
        Log::channel('single')->info('Payment verified', [
            'transaction_id' => $transactionId,
            'result' => $result,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log payment query.
     */
    public function logQuery(string $type, array $data, array $response): void
    {
        Log::channel('single')->info('Payment query', [
            'type' => $type,
            'data' => $data,
            'response' => $response,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log payment callback.
     */
    public function logCallback(string $provider, array $callbackData): void
    {
        Log::channel('single')->info('Payment callback received', [
            'provider' => $provider,
            'merchant_oid' => $callbackData['merchant_oid'] ?? null,
            'status' => $callbackData['status'] ?? null,
            'callback_data' => $callbackData,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
