<?php

namespace App\PaymentGateways;

use App\Models\Payment;
use App\Models\PaymentMethod;

interface PaymentProviderInterface
{
    /**
     * Initialize a new payment session.
     *
     * @param array $data Payment initialization data
     * @return array Payment response with token/URL
     */
    public function initializePayment(array $data): array;

    /**
     * Verify payment status.
     *
     * @param string $transactionId Transaction identifier
     * @param string $chargeId Charge identifier
     * @return array Verification result
     */
    public function verifyPayment(string $transactionId, string $chargeId = null): array;

    /**
     * Query payment status from provider.
     *
     * @param string $merchantOid Merchant order ID
     * @return array Payment status
     */
    public function queryPaymentStatus(string $merchantOid): array;

    /**
     * Process a refund.
     *
     * @param Payment $payment
     * @param float $amount
     * @return array Refund result
     */
    public function refund(Payment $payment, float $amount): array;

    /**
     * Add a new payment method (card).
     *
     * @param array $data Card data
     * @return array Payment method data with utoken
     */
    public function addPaymentMethod(array $data): array;

    /**
     * List saved payment methods for a contact.
     *
     * @param string $locationId
     * @param string $contactId
     * @param string $utoken User token
     * @return array List of payment methods
     */
    public function listPaymentMethods(string $locationId, string $contactId, string $utoken = null): array;

    /**
     * Charge a saved payment method.
     *
     * @param PaymentMethod $paymentMethod
     * @param array $data Charge data
     * @return array Charge result
     */
    public function chargePaymentMethod(PaymentMethod $paymentMethod, array $data): array;

    /**
     * Delete a saved payment method.
     *
     * @param PaymentMethod $paymentMethod
     * @return bool Success status
     */
    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool;

    /**
     * Validate callback data from payment provider.
     *
     * @param array $callbackData
     * @return bool Validation result
     */
    public function validateCallback(array $callbackData): bool;

    /**
     * Get provider name.
     *
     * @return string
     */
    public function getProviderName(): string;
}
