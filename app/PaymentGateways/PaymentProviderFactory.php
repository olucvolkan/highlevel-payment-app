<?php

namespace App\PaymentGateways;

use App\Models\HLAccount;
use InvalidArgumentException;

class PaymentProviderFactory
{
    /**
     * Create a payment provider instance.
     *
     * @param string $provider Provider name (paytr, stripe, iyzico, etc.)
     * @param HLAccount|null $account Account with provider credentials
     * @return PaymentProviderInterface
     * @throws InvalidArgumentException
     */
    public static function make(string $provider, ?HLAccount $account = null): PaymentProviderInterface
    {
        return match (strtolower($provider)) {
            'paytr' => new PayTRPaymentProvider($account),
            // Future providers can be added here:
            // 'stripe' => new StripePaymentProvider($account),
            // 'iyzico' => new IyzicoPaymentProvider($account),
            default => throw new InvalidArgumentException("Unsupported payment provider: {$provider}")
        };
    }

    /**
     * Get the default payment provider.
     *
     * @param HLAccount|null $account Account with provider credentials
     * @return PaymentProviderInterface
     */
    public static function default(?HLAccount $account = null): PaymentProviderInterface
    {
        $defaultProvider = config('services.payment.default_provider', 'paytr');

        return static::make($defaultProvider, $account);
    }

    /**
     * Create a provider for a specific account.
     *
     * @param HLAccount $account
     * @param string|null $provider
     * @return PaymentProviderInterface
     */
    public static function forAccount(HLAccount $account, ?string $provider = null): PaymentProviderInterface
    {
        $provider = $provider ?? config('services.payment.default_provider', 'paytr');
        
        return static::make($provider, $account);
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
