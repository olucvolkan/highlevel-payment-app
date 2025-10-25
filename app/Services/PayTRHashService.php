<?php

namespace App\Services;

class PayTRHashService
{
    protected string $merchantKey;
    protected string $merchantSalt;

    public function __construct()
    {
        $this->merchantKey = config('services.paytr.merchant_key');
        $this->merchantSalt = config('services.paytr.merchant_salt');
    }

    /**
     * Generate payment initialization token.
     */
    public function generatePaymentToken(array $data): string
    {
        $hashStr = $data['merchant_id'] .
                   $data['user_ip'] .
                   $data['merchant_oid'] .
                   $data['email'] .
                   $data['payment_amount'] .
                   $data['payment_type'] .
                   $data['installment_count'] .
                   $data['currency'] .
                   $data['test_mode'] .
                   $data['non_3d'];

        return $this->generateHash($hashStr);
    }

    /**
     * Generate callback validation hash.
     */
    public function generateCallbackHash(string $merchantOid, string $status, string $totalAmount): string
    {
        $hashStr = $merchantOid . $this->merchantSalt . $status . $totalAmount;

        return $this->generateHash($hashStr);
    }

    /**
     * Validate PayTR callback.
     */
    public function validateCallback(array $callbackData): bool
    {
        $receivedHash = $callbackData['hash'] ?? '';

        $calculatedHash = $this->generateCallbackHash(
            $callbackData['merchant_oid'],
            $callbackData['status'],
            $callbackData['total_amount']
        );

        return hash_equals($receivedHash, $calculatedHash);
    }

    /**
     * Generate status query token.
     */
    public function generateStatusQueryToken(string $merchantOid): string
    {
        $hashStr = $merchantOid . $this->merchantSalt;

        return $this->generateHash($hashStr);
    }

    /**
     * Generate refund token.
     */
    public function generateRefundToken(string $merchantOid, int $returnAmount): string
    {
        $hashStr = $merchantOid . $returnAmount . $this->merchantSalt;

        return $this->generateHash($hashStr);
    }

    /**
     * Generate card storage token.
     */
    public function generateCardToken(array $data): string
    {
        $hashStr = $data['merchant_id'] .
                   $data['user_ip'] .
                   $data['merchant_oid'] .
                   $data['email'] .
                   $data['payment_amount'] .
                   $data['payment_type'] .
                   ($data['installment_count'] ?? '0') .
                   $data['currency'] .
                   $data['test_mode'] .
                   $data['non_3d'];

        return $this->generateHash($hashStr);
    }

    /**
     * Generate HMAC-SHA256 hash.
     */
    protected function generateHash(string $data): string
    {
        return base64_encode(
            hash_hmac('sha256', $data . $this->merchantSalt, $this->merchantKey, true)
        );
    }

    /**
     * Convert amount to kuruş (cents).
     */
    public function convertToKurus(float $amount): int
    {
        return (int) ($amount * 100);
    }

    /**
     * Convert kuruş to amount.
     */
    public function convertFromKurus(int $kurus): float
    {
        return $kurus / 100;
    }
}
