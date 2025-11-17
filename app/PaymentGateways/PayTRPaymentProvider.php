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
        $userIp = request()->getClientIp();
        $email = $data['email'];
        $paymentAmount = $this->convertToKurus($data['amount']);
        // PayTR uses 'TL' not 'TRY' for Turkish Lira
        $currency = 'TL';

        // Prepare user basket
        $userBasket = $this->prepareUserBasket($data['items'] ?? []);
        $userBasketEncoded = base64_encode($userBasket);

        $noInstallment = $data['no_installment'] ?? 0;
        $maxInstallment = $data['max_installment'] ?? 0;
        $testMode = $this->testMode ? '1' : '0';

        // Generate PayTR token - MUST match exact order from PayTR documentation
        // Order: merchant_id + user_ip + merchant_oid + email + payment_amount +
        //        user_basket + no_installment + max_installment + currency + test_mode
        // Then append merchant_salt when calling hash_hmac (NOT in the hash_str itself)
        $hashStr = $this->merchantId . $userIp . $merchantOid . $email .
                   $paymentAmount . $userBasketEncoded . $noInstallment . $maxInstallment .
                   $currency . $testMode;
        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr . $this->merchantSalt, $this->merchantKey, true));

        // Debug: Log hash generation details
        Log::info('PayTR Token Generation', [
            'merchant_id' => $this->merchantId,
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_amount' => $paymentAmount,
            'user_basket_encoded' => $userBasketEncoded,
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'currency' => $currency,
            'test_mode' => $testMode,
            'hash_str_length' => strlen($hashStr),
            'merchant_salt_length' => strlen($this->merchantSalt),
            'merchant_key_length' => strlen($this->merchantKey),
            'generated_token' => $paytrToken,
        ]);

        // Prepare request data - match PayTR API documentation requirements
        $requestData = [
            'merchant_id' => $this->merchantId,
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_amount' => $paymentAmount,
            'paytr_token' => $paytrToken,
            'user_basket' => $userBasketEncoded,
            'debug_on' => $testMode,
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'user_name' => $data['user_name'] ?? 'Customer',
            'user_address' => $data['user_address'] ?? '',
            'user_phone' => $data['user_phone'] ?? '0000000000',
            'merchant_ok_url' => $data['success_url'] ?? config('app.url') . '/payments/success',
            'merchant_fail_url' => $data['fail_url'] ?? config('app.url') . '/payments/error',
            'timeout_limit' => $data['timeout_limit'] ?? '30',
            'currency' => $currency,
            'test_mode' => $testMode,
        ];

        // Add optional card storage parameters if provided
        if (isset($data['utoken'])) {
            $requestData['utoken'] = $data['utoken'];
        }

        if (isset($data['store_card']) && $data['store_card']) {
            $requestData['store_card'] = '1';
        }

        // Add optional payment type if needed (non_3d, etc.)
        if (isset($data['non_3d'])) {
            $requestData['non_3d'] = $data['non_3d'];
        }

        if (isset($data['payment_type'])) {
            $requestData['payment_type'] = $data['payment_type'];
        }

        if (isset($data['installment_count']) && $data['installment_count'] > 0) {
            $requestData['installment_count'] = $data['installment_count'];
        }

        try {
            Log::info('PayTR payment initialization request', [
                'url' => $this->apiUrl . '/odeme/api/get-token',
                'requestData' => $requestData,
                'requestDataKeys' => array_keys($requestData),
            ]);

            // Use cURL directly as per PayTR documentation
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/odeme/api/get-token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $responseBody = @curl_exec($ch);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \Exception('PAYTR IFRAME connection error: ' . $error);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($responseBody, true);

            Log::info('PayTR payment initialization response', [
                'status_code' => $httpCode,
                'response_body' => $responseBody,
                'result' => $result,
            ]);

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
        $token = base64_encode(hash_hmac    ('sha256', $hashStr, $this->merchantKey, true));

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
