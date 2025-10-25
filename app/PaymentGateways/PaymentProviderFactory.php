<?php

namespace App\PaymentGateways;

use InvalidArgumentException;

class PaymentProviderFactory
{
    /**
     * Create a payment provider instance.
     *
     * @param string $provider Provider name (paytr, stripe, iyzico, etc.)
     * @return PaymentProviderInterface
     * @throws InvalidArgumentException
     */
    public static function make(string $provider): PaymentProviderInterface
    {
        return match (strtolower($provider)) {
            'paytr' => new PayTRPaymentProvider(),
            // Future providers can be added here:
            // 'stripe' => new StripePaymentProvider(),
            // 'iyzico' => new IyzicoPaymentProvider(),
            default => throw new InvalidArgumentException("Unsupported payment provider: {$provider}")
        };
    }

    /**
     * Get the default payment provider.
     *
     * @return PaymentProviderInterface
     */
    public static function default(): PaymentProviderInterface
    {
        $defaultProvider = config('services.payment.default_provider', 'paytr');

        return static::make($defaultProvider);
    }

    /**
     * Get list of supported providers.
     *
     * @return array
     */
    public static function getSupportedProviders(): array
    {
        return [
            'paytr' => [
                'name' => 'PayTR',
                'description' => 'Turkish payment gateway',
                'currencies' => ['TRY', 'USD', 'EUR'],
                'features' => ['card_storage', 'installments', 'refunds'],
            ],
            // Future providers:
            // 'stripe' => [...],
            // 'iyzico' => [...],
        ];
    }

    /**
     * Check if a provider is supported.
     *
     * @param string $provider
     * @return bool
     */
    public static function isSupported(string $provider): bool
    {
        return array_key_exists(strtolower($provider), static::getSupportedProviders());
    }
}
