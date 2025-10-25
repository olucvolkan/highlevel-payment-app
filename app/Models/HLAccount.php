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
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'scopes' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
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
}
