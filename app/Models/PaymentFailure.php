<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'hl_account_id',
        'location_id',
        'merchant_oid',
        'transaction_id',
        'provider',
        'error_code',
        'error_message',
        'failure_reason',
        'request_data',
        'response_data',
        'user_ip',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    /**
     * Get the payment that owns this failure.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the HighLevel account that owns this failure.
     */
    public function hlAccount(): BelongsTo
    {
        return $this->belongsTo(HLAccount::class);
    }

    /**
     * Scope to get failures by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to get failures by error code.
     */
    public function scopeByErrorCode($query, string $errorCode)
    {
        return $query->where('error_code', $errorCode);
    }

    /**
     * Scope to get recent failures.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
