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
        'integration_id',
        'config_id',
        'is_active',
        'scopes',
        'metadata',
        'paytr_merchant_id',
        'paytr_merchant_key',
        'paytr_merchant_salt',
        'paytr_test_mode',
        'paytr_configured',
        'paytr_configured_at',
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
        'paytr_merchant_key',
        'paytr_merchant_salt',
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
}
