<?php

namespace App\Repositories;

use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Collection;

class WebhookLogRepository
{
    /**
     * Find webhook log by ID.
     */
    public function find(int $id): ?WebhookLog
    {
        return WebhookLog::find($id);
    }

    /**
     * Get webhooks by type.
     */
    public function getByType(string $type, int $limit = 100): Collection
    {
        return WebhookLog::where('type', $type)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get webhooks by source.
     */
    public function getBySource(string $source, int $limit = 100): Collection
    {
        return WebhookLog::where('source', $source)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed webhooks.
     */
    public function getFailed(int $limit = 100): Collection
    {
        return WebhookLog::where('status', WebhookLog::STATUS_FAILED)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get webhooks for retry.
     */
    public function getForRetry(int $maxRetries = 3): Collection
    {
        return WebhookLog::where('status', WebhookLog::STATUS_FAILED)
            ->where('retry_count', '<', $maxRetries)
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();
    }

    /**
     * Get recent webhooks.
     */
    public function getRecent(int $hours = 24, int $limit = 100): Collection
    {
        return WebhookLog::where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create webhook log.
     */
    public function create(array $data): WebhookLog
    {
        return WebhookLog::create($data);
    }

    /**
     * Update webhook log.
     */
    public function update(WebhookLog $webhookLog, array $data): bool
    {
        return $webhookLog->update($data);
    }

    /**
     * Log incoming webhook.
     */
    public function logIncoming(string $source, string $event, array $payload): WebhookLog
    {
        return $this->create([
            'type' => WebhookLog::TYPE_INCOMING,
            'source' => $source,
            'event' => $event,
            'status' => WebhookLog::STATUS_SUCCESS,
            'payload' => $payload,
            'received_at' => now(),
        ]);
    }

    /**
     * Log outgoing webhook.
     */
    public function logOutgoing(string $source, string $event, string $url, array $payload): WebhookLog
    {
        return $this->create([
            'type' => WebhookLog::TYPE_OUTGOING,
            'source' => $source,
            'event' => $event,
            'status' => WebhookLog::STATUS_PENDING,
            'url' => $url,
            'payload' => $payload,
        ]);
    }

    /**
     * Get webhook statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_webhooks' => WebhookLog::count(),
            'incoming_webhooks' => WebhookLog::where('type', WebhookLog::TYPE_INCOMING)->count(),
            'outgoing_webhooks' => WebhookLog::where('type', WebhookLog::TYPE_OUTGOING)->count(),
            'failed_webhooks' => WebhookLog::where('status', WebhookLog::STATUS_FAILED)->count(),
            'success_webhooks' => WebhookLog::where('status', WebhookLog::STATUS_SUCCESS)->count(),
        ];
    }

    /**
     * Delete old webhook logs.
     */
    public function deleteOld(int $days = 30): int
    {
        return WebhookLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
