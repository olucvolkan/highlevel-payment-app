<?php

namespace App\PaymentGateways;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\HLAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayTRPaymentProvider implements PaymentProviderInterface
{
    protected string $merchantId;
    protected string $merchantKey;
    protected string $merchantSalt;
    protected string $apiUrl;
    protected bool $testMode;
    protected ?HLAccount $account;

    public function __construct(?HLAccount $account = null)
    {
        $this->account = $account;
        $this->apiUrl = config('services.paytr.api_url', 'https://www.paytr.com');
        
        if ($account && $account->hasPayTRCredentials()) {
            $credentials = $account->getPayTRCredentials();
            $this->merchantId = $credentials['merchant_id'];
            $this->merchantKey = $credentials['merchant_key'];
            $this->merchantSalt = $credentials['merchant_salt'];
            $this->testMode = $credentials['test_mode'];
        } else {
            // Fallback to config (for testing only - production should use database)
            $this->merchantId = config('services.paytr.merchant_id', '');
            $this->merchantKey = config('services.paytr.merchant_key', '');
            $this->merchantSalt = config('services.paytr.merchant_salt', '');
            $this->testMode = config('services.paytr.test_mode', true);
        }
    }

    /**
     * Initialize a new payment session.
     */
    public function initializePayment(array $data): array
    {
        if (empty($this->merchantId) || empty($this->merchantKey) || empty($this->merchantSalt)) {
            return [
                'success' => false,
                'error' => 'PayTR credentials not configured for this account',
            ];
        }
        $merchantOid = $data['merchant_oid'] ?? 'ORDER_' . time() . rand(1000, 9999);
        $userIp = $data['user_ip'] ?? request()->ip();
        $email = $data['email'];
        $paymentAmount = $this->convertToKurus($data['amount']);
        $currency = $data['currency'] ?? 'TL';

        // Prepare user basket
        $userBasket = $this->prepareUserBasket($data['items'] ?? []);

        $noInstallment = $data['no_installment'] ?? 1;
        $maxInstallment = $data['max_installment'] ?? 0;
        $paymentType = $data['payment_type'] ?? 'card';
        $installmentCount = $data['installment_count'] ?? 0;

        // Generate PayTR token
        $hashStr = $this->merchantId . $userIp . $merchantOid . $email .
                   $paymentAmount . $paymentType . $installmentCount . $currency .
                   ($this->testMode ? '1' : '0') . '0'; // non_3d is 0

        $token = base64_encode(hash_hmac('sha256', $hashStr . $this->merchantSalt, $this->merchantKey, true));

        // Prepare request data
        $requestData = [
            'merchant_id' => $this->merchantId,
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_type' => $paymentType,
            'payment_amount' => $paymentAmount,
            'currency' => $currency,
            'test_mode' => $this->testMode ? '1' : '0',
            'non_3d' => '0',
            'merchant_ok_url' => $data['success_url'] ?? config('app.url') . '/payments/success',
            'merchant_fail_url' => $data['fail_url'] ?? config('app.url') . '/payments/error',
            'user_name' => $data['user_name'] ?? 'Customer',
            'user_address' => $data['user_address'] ?? 'N/A',
            'user_phone' => $data['user_phone'] ?? '0000000000',
            'user_basket' => base64_encode($userBasket),
            'debug_on' => $this->testMode ? '1' : '0',
            'client_lang' => 'tr',
            'paytr_token' => $token,
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'installment_count' => $installmentCount,
        ];

        // Add utoken if provided (for card storage)
        if (isset($data['utoken'])) {
            $requestData['utoken'] = $data['utoken'];
        }

        // Add store_card flag if requested
        if (isset($data['store_card']) && $data['store_card']) {
            $requestData['store_card'] = '1';
        }

        try {
            $response = Http::asForm()->post($this->apiUrl . '/odeme/api/get-token', $requestData);

            $result = json_decode($response->body(), true);

            if ($result['status'] === 'success') {
                return [
                    'success' => true,
                    'token' => $result['token'],
                    'iframe_url' => $this->apiUrl . '/odeme/guvenli/' . $result['token'],
                    'merchant_oid' => $merchantOid,
                ];
            }

            return [
                'success' => false,
                'error' => $result['reason'] ?? 'Payment initialization failed',
            ];
        } catch (\Exception $e) {
            Log::error('PayTR payment initialization failed', [
                'error' => $e->getMessage(),
                'data' => $requestData,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment status.
     */
    public function verifyPayment(string $transactionId, string $chargeId = null): array
    {
        // For PayTR, verification happens via callback
        // This method checks if payment exists and is successful
        $payment = Payment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return [
                'success' => false,
                'failed' => true,
            ];
        }

        if ($payment->status === Payment::STATUS_SUCCESS) {
            return ['success' => true];
        }

        if ($payment->status === Payment::STATUS_FAILED) {
            return ['failed' => true];
        }

        return ['success' => false]; // Pending
    }

    /**
     * Query payment status from PayTR.
     */
    public function queryPaymentStatus(string $merchantOid): array
    {
        $hashStr = $merchantOid . $this->merchantSalt;
        $token = base64_encode(hash_hmac('sha256', $hashStr, $this->merchantKey, true));

        try {
            $response = Http::asForm()->post($this->apiUrl . '/odeme/durum-sorgu', [
                'merchant_id' => $this->merchantId,
                'merchant_oid' => $merchantOid,
                'paytr_token' => $token,
            ]);

            return json_decode($response->body(), true);
        } catch (\Exception $e) {
            Log::error('PayTR status query failed', [
                'merchant_oid' => $merchantOid,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process a refund.
     */
    public function refund(Payment $payment, float $amount): array
    {
        $returnAmount = $this->convertToKurus($amount);

        $hashStr = $payment->merchant_oid . $returnAmount . $this->merchantSalt;
        $token = base64_encode(hash_hmac('sha256', $hashStr, $this->merchantKey, true));

        try {
            $response = Http::asForm()->post($this->apiUrl . '/odeme/iade', [
                'merchant_id' => $this->merchantId,
                'merchant_oid' => $payment->merchant_oid,
                'return_amount' => $returnAmount,
                'paytr_token' => $token,
            ]);

            $result = json_decode($response->body(), true);

            return [
                'success' => $result['status'] === 'success',
                'message' => $result['message'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('PayTR refund failed', [
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
     * Add a new payment method (card).
     */
    public function addPaymentMethod(array $data): array
    {
        // PayTR handles card storage via payment flow with store_card=1
        // This returns utoken in callback
        return $this->initializePayment(array_merge($data, ['store_card' => true]));
    }

    /**
     * List saved payment methods.
     */
    public function listPaymentMethods(string $locationId, string $contactId, string $utoken = null): array
    {
        // PayTR doesn't have a direct API to list cards
        // We return cards stored in our database
        $query = PaymentMethod::where('location_id', $locationId)
            ->where('contact_id', $contactId)
            ->where('provider', 'paytr');

        if ($utoken) {
            $query->where('utoken', $utoken);
        }

        $methods = $query->get();

        return $methods->map(function ($method) {
            return [
                'id' => $method->id,
                'type' => 'card',
                'title' => $method->card_brand ?? 'Card',
                'subTitle' => '**** ' . $method->card_last_four,
                'expiry' => $method->expiry_month . '/' . $method->expiry_year,
                'imageUrl' => $this->getCardBrandImage($method->card_brand),
            ];
        })->toArray();
    }

    /**
     * Charge a saved payment method.
     */
    public function chargePaymentMethod(PaymentMethod $paymentMethod, array $data): array
    {
        // Use stored card token to make payment
        return $this->initializePayment(array_merge($data, [
            'utoken' => $paymentMethod->utoken,
            'ctoken' => $paymentMethod->ctoken,
        ]));
    }

    /**
     * Delete a saved payment method.
     */
    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        // PayTR doesn't have direct API for deleting cards
        // We just remove from our database
        return $paymentMethod->delete();
    }

    /**
     * Validate callback data from PayTR.
     */
    public function validateCallback(array $callbackData): bool
    {
        $merchantOid = $callbackData['merchant_oid'];
        $status = $callbackData['status'];
        $totalAmount = $callbackData['total_amount'];
        $hash = $callbackData['hash'];

        $calculatedHash = base64_encode(
            hash_hmac('sha256', $merchantOid . $this->merchantSalt . $status . $totalAmount, $this->merchantKey, true)
        );

        return $hash === $calculatedHash;
    }

    /**
     * Get provider name.
     */
    public function getProviderName(): string
    {
        return 'paytr';
    }

    /**
     * Convert amount to kuruş (cents).
     */
    protected function convertToKurus(float $amount): int
    {
        return (int) ($amount * 100);
    }

    /**
     * Prepare user basket for PayTR.
     */
    protected function prepareUserBasket(array $items): string
    {
        if (empty($items)) {
            return json_encode([['Product', '1.00', 1]]);
        }

        $basket = [];
        foreach ($items as $item) {
            $basket[] = [
                $item['name'] ?? 'Product',
                number_format($item['price'] ?? 0, 2, '.', ''),
                $item['quantity'] ?? 1,
            ];
        }

        return json_encode($basket);
    }

    /**
     * Get card brand image URL.
     */
    protected function getCardBrandImage(string $brand = null): string
    {
        $brands = [
            'visa' => 'https://cdn.paytr.com/images/visa.png',
            'mastercard' => 'https://cdn.paytr.com/images/mastercard.png',
            'amex' => 'https://cdn.paytr.com/images/amex.png',
        ];

        return $brands[strtolower($brand)] ?? 'https://cdn.paytr.com/images/card.png';
    }
}
