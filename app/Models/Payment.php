<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hl_account_id',
        'location_id',
        'contact_id',
        'merchant_oid',
        'transaction_id',
        'charge_id',
        'subscription_id',
        'order_id',
        'provider',
        'provider_payment_id',
        'amount',
        'currency',
        'status',
        'payment_mode',
        'payment_type',
        'installment_count',
        'user_ip',
        'email',
        'user_basket',
        'metadata',
        'error_message',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'installment_count' => 'integer',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Payment statuses.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIAL_REFUND = 'partial_refund';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the HighLevel account that owns this payment.
     */
    public function hlAccount(): BelongsTo
    {
        return $this->belongsTo(HLAccount::class);
    }

    /**
     * Get the payment failures for this payment.
     */
    public function failures(): HasMany
    {
        return $this->hasMany(PaymentFailure::class);
    }

    /**
     * Check if the payment is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if the payment has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Mark payment as successful.
     */
    public function markAsSuccess(string $chargeId = null): self
    {
        $this->status = self::STATUS_SUCCESS;
        $this->paid_at = now();

        if ($chargeId) {
            $this->charge_id = $chargeId;
        }

        $this->save();

        return $this;
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(string $errorMessage = null): self
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->save();

        return $this;
    }

    /**
     * Scope to get payments by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get payments by location.
     */
    public function scopeByLocation($query, string $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Scope to get successful payments.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope to get failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
