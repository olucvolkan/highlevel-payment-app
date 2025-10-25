<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'source',
        'event',
        'status',
        'url',
        'method',
        'headers',
        'payload',
        'response',
        'response_code',
        'error_message',
        'retry_count',
        'sent_at',
        'received_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'response' => 'array',
        'response_code' => 'integer',
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * Webhook types.
     */
    const TYPE_INCOMING = 'incoming';
    const TYPE_OUTGOING = 'outgoing';

    /**
     * Webhook sources.
     */
    const SOURCE_PAYTR = 'paytr';
    const SOURCE_HIGHLEVEL = 'highlevel';

    /**
     * Webhook statuses.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    /**
     * Mark webhook as success.
     */
    public function markAsSuccess(array $response = null, int $responseCode = null): self
    {
        $this->status = self::STATUS_SUCCESS;
        $this->response = $response;
        $this->response_code = $responseCode;
        $this->save();

        return $this;
    }

    /**
     * Mark webhook as failed.
     */
    public function markAsFailed(string $errorMessage = null, int $responseCode = null): self
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->response_code = $responseCode;
        $this->retry_count++;
        $this->save();

        return $this;
    }

    /**
     * Scope to get webhooks by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get webhooks by source.
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }
}
