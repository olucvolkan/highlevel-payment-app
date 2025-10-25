<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hl_account_id',
        'location_id',
        'contact_id',
        'provider',
        'utoken',
        'ctoken',
        'card_type',
        'card_last_four',
        'card_brand',
        'expiry_month',
        'expiry_year',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the HighLevel account that owns this payment method.
     */
    public function hlAccount(): BelongsTo
    {
        return $this->belongsTo(HLAccount::class);
    }

    /**
     * Check if the card is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->expiry_month || !$this->expiry_year) {
            return false;
        }

        $expiryDate = \Carbon\Carbon::createFromDate($this->expiry_year, $this->expiry_month, 1)->endOfMonth();

        return $expiryDate->isPast();
    }

    /**
     * Get formatted card display.
     */
    public function getCardDisplay(): string
    {
        return ($this->card_brand ?? 'Card') . ' **** ' . $this->card_last_four;
    }

    /**
     * Scope to get payment methods by contact.
     */
    public function scopeByContact($query, string $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Scope to get default payment method.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
