<?php

namespace App\Services;

use App\Models\HLAccount;
use App\Models\Payment;
use App\Models\PaymentFailure;
use App\Models\PaymentMethod;
use App\PaymentGateways\PaymentProviderFactory;
use App\Logging\PaymentLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        protected HighLevelService $highLevelService,
        protected PayTRHashService $hashService,
        protected PaymentLogger $paymentLogger
    ) {
    }

    /**
     * Create a new payment.
     */
    public function createPayment(HLAccount $account, array $data): array
    {
        try {
            $provider = PaymentProviderFactory::make($data['provider'] ?? 'paytr');

            // Generate unique merchant order ID
            $merchantOid = 'ORDER_' . time() . '_' . rand(1000, 9999);

            // Create payment record
            $payment = Payment::create([
                'hl_account_id' => $account->id,
                'location_id' => $account->location_id,
                'contact_id' => $data['contactId'] ?? null,
                'merchant_oid' => $merchantOid,
                'transaction_id' => $data['transactionId'] ?? null,
                'subscription_id' => $data['subscriptionId'] ?? null,
                'order_id' => $data['orderId'] ?? null,
                'provider' => $provider->getProviderName(),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'TRY',
                'status' => Payment::STATUS_PENDING,
                'payment_mode' => $data['mode'] ?? 'payment',
                'payment_type' => $data['payment_type'] ?? 'card',
                'installment_count' => $data['installment_count'] ?? 0,
                'user_ip' => $data['user_ip'] ?? request()->ip(),
                'email' => $data['email'],
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Initialize payment with provider
            $result = $provider->initializePayment([
                'merchant_oid' => $merchantOid,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'TRY',
                'email' => $data['email'],
                'user_ip' => $data['user_ip'] ?? request()->ip(),
                'user_name' => $data['user_name'] ?? 'Customer',
                'user_phone' => $data['user_phone'] ?? '0000000000',
                'user_address' => $data['user_address'] ?? 'N/A',
                'items' => $data['items'] ?? [],
                'success_url' => $data['success_url'] ?? config('app.url') . '/payments/success',
                'fail_url' => $data['fail_url'] ?? config('app.url') . '/payments/error',
                'installment_count' => $data['installment_count'] ?? 0,
                'utoken' => $data['utoken'] ?? null,
                'store_card' => $data['store_card'] ?? false,
            ]);

            if ($result['success']) {
                $payment->update([
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'payment_token' => $result['token'] ?? null,
                        'iframe_url' => $result['iframe_url'] ?? null,
                    ]),
                ]);

                $this->paymentLogger->logPaymentInitialized($payment, $result);

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'iframe_url' => $result['iframe_url'],
                    'token' => $result['token'],
                    'merchant_oid' => $merchantOid,
                ];
            }

            $payment->markAsFailed($result['error'] ?? 'Payment initialization failed');

            $this->logPaymentFailure($payment, $result, $data);

            return [
                'success' => false,
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            Log::error('Payment creation failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process PayTR callback.
     */
    public function processCallback(array $callbackData): bool
    {
        try {
            // Validate callback hash
            if (!$this->hashService->validateCallback($callbackData)) {
                Log::error('Invalid PayTR callback hash', ['data' => $callbackData]);
                return false;
            }

            $payment = Payment::where('merchant_oid', $callbackData['merchant_oid'])->first();

            if (!$payment) {
                Log::error('Payment not found for callback', ['merchant_oid' => $callbackData['merchant_oid']]);
                return false;
            }

            DB::transaction(function () use ($payment, $callbackData) {
                if ($callbackData['status'] === 'success') {
                    $payment->markAsSuccess($callbackData['payment_id'] ?? null);
                    $payment->update([
                        'provider_payment_id' => $callbackData['payment_id'] ?? null,
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'callback_data' => $callbackData,
                        ]),
                    ]);

                    // Handle card storage if utoken is provided
                    if (isset($callbackData['utoken'])) {
                        $this->storePaymentMethod($payment, $callbackData);
                    }

                    // Send webhook to HighLevel
                    $this->highLevelService->sendPaymentCaptured($payment->hlAccount, [
                        'chargeId' => $payment->charge_id,
                        'transactionId' => $payment->transaction_id,
                        'amount' => (int) ($payment->amount * 100),
                        'chargedAt' => $payment->paid_at->timestamp,
                    ]);

                    $this->paymentLogger->logPaymentSuccess($payment, $callbackData);
                } else {
                    $payment->markAsFailed($callbackData['failed_reason_msg'] ?? 'Payment failed');

                    $this->logPaymentFailure($payment, $callbackData, $callbackData);

                    $this->paymentLogger->logPaymentFailed($payment, $callbackData);
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Callback processing failed', [
                'error' => $e->getMessage(),
                'data' => $callbackData,
            ]);

            return false;
        }
    }

    /**
     * Verify payment status.
     */
    public function verifyPayment(string $transactionId, string $chargeId = null): array
    {
        $payment = Payment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return [
                'success' => false,
                'failed' => true,
            ];
        }

        $provider = PaymentProviderFactory::make($payment->provider);

        return $provider->verifyPayment($transactionId, $chargeId);
    }

    /**
     * Process refund.
     */
    public function processRefund(Payment $payment, float $amount): array
    {
        try {
            $provider = PaymentProviderFactory::make($payment->provider);

            $result = $provider->refund($payment, $amount);

            if ($result['success']) {
                $refundedAmount = $payment->metadata['refunded_amount'] ?? 0;
                $totalRefunded = $refundedAmount + $amount;

                if ($totalRefunded >= $payment->amount) {
                    $payment->status = Payment::STATUS_REFUNDED;
                } else {
                    $payment->status = Payment::STATUS_PARTIAL_REFUND;
                }

                $payment->metadata = array_merge($payment->metadata ?? [], [
                    'refunded_amount' => $totalRefunded,
                    'refund_history' => array_merge($payment->metadata['refund_history'] ?? [], [[
                        'amount' => $amount,
                        'date' => now()->toIso8601String(),
                    ]]),
                ]);
                $payment->save();

                $this->paymentLogger->logRefund($payment, $amount, $result);

                return [
                    'success' => true,
                    'message' => 'Refund processed successfully',
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Refund failed',
            ];
        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'payment_id' => $payment->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Store payment method (card).
     */
    protected function storePaymentMethod(Payment $payment, array $callbackData): void
    {
        if (!isset($callbackData['utoken']) || !$payment->contact_id) {
            return;
        }

        PaymentMethod::updateOrCreate(
            [
                'hl_account_id' => $payment->hl_account_id,
                'location_id' => $payment->location_id,
                'contact_id' => $payment->contact_id,
                'utoken' => $callbackData['utoken'],
            ],
            [
                'provider' => $payment->provider,
                'ctoken' => $callbackData['ctoken'] ?? null,
                'card_type' => $callbackData['card_type'] ?? null,
                'card_last_four' => $callbackData['card_last_four'] ?? substr($callbackData['card_pan'] ?? '', -4),
                'card_brand' => $callbackData['card_brand'] ?? $this->detectCardBrand($callbackData['card_pan'] ?? ''),
                'expiry_month' => $callbackData['card_exp_month'] ?? null,
                'expiry_year' => $callbackData['card_exp_year'] ?? null,
                'is_default' => false,
            ]
        );
    }

    /**
     * Log payment failure.
     */
    protected function logPaymentFailure(Payment $payment, array $response, array $request): void
    {
        PaymentFailure::create([
            'payment_id' => $payment->id,
            'hl_account_id' => $payment->hl_account_id,
            'location_id' => $payment->location_id,
            'merchant_oid' => $payment->merchant_oid,
            'transaction_id' => $payment->transaction_id,
            'provider' => $payment->provider,
            'error_code' => $response['failed_reason_code'] ?? null,
            'error_message' => $response['failed_reason_msg'] ?? $response['error'] ?? 'Unknown error',
            'failure_reason' => $response['failed_reason_msg'] ?? null,
            'request_data' => $request,
            'response_data' => $response,
            'user_ip' => $payment->user_ip,
        ]);
    }

    /**
     * Detect card brand from PAN.
     */
    protected function detectCardBrand(string $pan): string
    {
        $firstDigit = substr($pan, 0, 1);
        $firstTwo = substr($pan, 0, 2);

        return match (true) {
            $firstDigit === '4' => 'visa',
            in_array($firstTwo, ['51', '52', '53', '54', '55']) => 'mastercard',
            in_array($firstTwo, ['34', '37']) => 'amex',
            default => 'unknown',
        };
    }
}
