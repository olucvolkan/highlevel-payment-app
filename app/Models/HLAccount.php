<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HLAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hl_accounts';

    protected $fillable = [
        'location_id',
        'company_id',
        'user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'company_access_token',
        'location_access_token',
        'location_refresh_token',
        'token_type',
        'third_party_provider_id',
        'integration_id',
        'config_id',
        'whitelabel_provider_id',
        'provider_callback_url',
        'is_active',
        'scopes',
        'metadata',
        'paytr_merchant_id',
        'paytr_merchant_key',
        'paytr_merchant_salt',
        'paytr_test_mode',
        'paytr_configured',
        'paytr_configured_at',
        'api_key_live',
        'api_key_test',
        'publishable_key_live',
        'publishable_key_test',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'paytr_configured_at' => 'datetime',
        'is_active' => 'boolean',
        'paytr_test_mode' => 'boolean',
        'paytr_configured' => 'boolean',
        'scopes' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'company_access_token',
        'location_access_token',
        'location_refresh_token',
        'paytr_merchant_key',
        'paytr_merchant_salt',
        'api_key_live',
        'api_key_test',
        'publishable_key_live',
        'publishable_key_test',
    ];

    /**
     * Get the payments for this account.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the payment methods for this account.
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get the payment failures for this account.
     */
    public function paymentFailures(): HasMany
    {
        return $this->hasMany(PaymentFailure::class);
    }

    /**
     * Get the activity logs for this account.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    /**
     * Check if the access token is expired.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Scope to get active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get accounts by location ID.
     */
    public function scopeByLocation($query, string $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Check if PayTR is configured for this account.
     */
    public function hasPayTRCredentials(): bool
    {
        return $this->paytr_configured && 
               !empty($this->paytr_merchant_id) && 
               !empty($this->paytr_merchant_key) && 
               !empty($this->paytr_merchant_salt);
    }

    /**
     * Get PayTR credentials for this account.
     */
    public function getPayTRCredentials(): array
    {
        return [
            'merchant_id' => $this->paytr_merchant_id,
            'merchant_key' => decrypt($this->paytr_merchant_key),
            'merchant_salt' => decrypt($this->paytr_merchant_salt),
            'test_mode' => $this->paytr_test_mode,
        ];
    }

    /**
     * Set PayTR credentials for this account.
     */
    public function setPayTRCredentials(array $credentials): void
    {
        $this->update([
            'paytr_merchant_id' => $credentials['merchant_id'],
            'paytr_merchant_key' => encrypt($credentials['merchant_key']),
            'paytr_merchant_salt' => encrypt($credentials['merchant_salt']),
            'paytr_test_mode' => $credentials['test_mode'] ?? true,
            'paytr_configured' => true,
            'paytr_configured_at' => now(),
        ]);
    }

    /**
     * Get the appropriate token for location-specific operations.
     * Returns the location access token if available.
     *
     * @return string|null
     */
    public function getLocationAccessToken(): ?string
    {
        return $this->location_access_token;
    }

    /**
     * Check if the stored token is a Company token.
     *
     * @return bool
     */
    public function isCompanyToken(): bool
    {
        return $this->token_type === 'Company';
    }

    /**
     * Check if the stored token is a Location token.
     *
     * @return bool
     */
    public function isLocationToken(): bool
    {
        return $this->token_type === 'Location';
    }

    /**
     * Check if location token needs to be obtained via exchange.
     *
     * @return bool
     */
    public function needsLocationTokenExchange(): bool
    {
        return empty($this->location_access_token) && !empty($this->access_token);
    }

    /**
     * Get the best available access token for the given context.
     * Prefers location token for location-specific operations.
     *
     * @param string $context 'location' or 'company'
     * @return string|null
     */
    public function getBestAccessToken(string $context = 'location'): ?string
    {
        if ($context === 'location' && $this->location_access_token) {
            return $this->location_access_token;
        }

        if ($context === 'company' && $this->company_access_token) {
            return $this->company_access_token;
        }

        // Fallback to the generic access_token
        return $this->access_token;
    }

    /**
     * Generate unique API keys for HighLevel config.
     * Creates both test and live mode keys using hash-based generation.
     *
     * @return array Generated keys
     */
    public function generateApiKeys(): array
    {
        $timestamp = now()->timestamp;
        $appKey = config('app.key');

        $keys = [
            'api_key_live' => hash_hmac('sha256',
                $this->location_id . ':live:api:' . $timestamp,
                $appKey
            ),
            'api_key_test' => hash_hmac('sha256',
                $this->location_id . ':test:api:' . $timestamp,
                $appKey
            ),
            'publishable_key_live' => hash_hmac('sha256',
                $this->location_id . ':live:publishable:' . $timestamp,
                $appKey
            ),
            'publishable_key_test' => hash_hmac('sha256',
                $this->location_id . ':test:publishable:' . $timestamp,
                $appKey
            ),
        ];

        // Store the generated keys
        $this->update($keys);

        return $keys;
    }

    /**
     * Check if API keys are configured.
     *
     * @return bool
     */
    public function hasApiKeys(): bool
    {
        return !empty($this->api_key_live) &&
               !empty($this->api_key_test) &&
               !empty($this->publishable_key_live) &&
               !empty($this->publishable_key_test);
    }

    /**
     * Get API keys for HighLevel config.
     *
     * @return array
     */
    public function getApiKeys(): array
    {
        return [
            'live' => [
                'apiKey' => $this->api_key_live,
                'publishableKey' => $this->publishable_key_live,
            ],
            'test' => [
                'apiKey' => $this->api_key_test,
                'publishableKey' => $this->publishable_key_test,
            ],
        ];
    }

    /**
     * Validate an API key for query requests.
     *
     * @param string $apiKey The key to validate
     * @return bool
     */
    public function isValidApiKey(string $apiKey): bool
    {
        return $apiKey === $this->api_key_live || $apiKey === $this->api_key_test;
    }
}
