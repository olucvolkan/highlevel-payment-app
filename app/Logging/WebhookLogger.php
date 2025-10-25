<?php

namespace App\Logging;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;

class WebhookLogger
{
    /**
     * Log incoming webhook.
     */
    public function logIncoming(string $source, array $payload, string $event = null, int $statusCode = 200): WebhookLog
    {
        $webhookLog = WebhookLog::create([
            'type' => WebhookLog::TYPE_INCOMING,
            'source' => $source,
            'event' => $event ?? $source,
            'status' => $statusCode >= 200 && $statusCode < 300 ? WebhookLog::STATUS_SUCCESS : WebhookLog::STATUS_FAILED,
            'payload' => $payload,
            'response_code' => $statusCode,
            'received_at' => now(),
        ]);

        Log::channel('single')->info('Incoming webhook received', [
            'webhook_id' => $webhookLog->id,
            'source' => $source,
            'event' => $event ?? $source,
            'payload' => $payload,
            'status_code' => $statusCode,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $webhookLog;
    }

    /**
     * Log outgoing webhook.
     */
    public function logOutgoing(string $destination, string $event, array $payload, string $url): WebhookLog
    {
        $webhookLog = WebhookLog::create([
            'type' => WebhookLog::TYPE_OUTGOING,
            'source' => $destination,
            'event' => $event,
            'status' => WebhookLog::STATUS_PENDING,
            'url' => $url,
            'payload' => $payload,
        ]);

        Log::channel('single')->info('Outgoing webhook prepared', [
            'webhook_id' => $webhookLog->id,
            'destination' => $destination,
            'event' => $event,
            'url' => $url,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $webhookLog;
    }

    /**
     * Log webhook success.
     */
    public function logSuccess(WebhookLog $webhookLog, array $response, int $statusCode): void
    {
        $webhookLog->update([
            'status' => WebhookLog::STATUS_SUCCESS,
            'response' => $response,
            'response_code' => $statusCode,
            'sent_at' => now(),
        ]);

        Log::channel('single')->info('Webhook sent successfully', [
            'webhook_id' => $webhookLog->id,
            'source' => $webhookLog->source,
            'event' => $webhookLog->event,
            'status_code' => $statusCode,
            'response' => $response,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log webhook failure.
     */
    public function logFailure(WebhookLog $webhookLog, string $error, int $statusCode = null): void
    {
        $webhookLog->update([
            'status' => WebhookLog::STATUS_FAILED,
            'error_message' => $error,
            'response_code' => $statusCode,
            'retry_count' => $webhookLog->retry_count + 1,
            'sent_at' => now(),
        ]);

        Log::channel('single')->error('Webhook failed', [
            'webhook_id' => $webhookLog->id,
            'source' => $webhookLog->source,
            'event' => $webhookLog->event,
            'error' => $error,
            'status_code' => $statusCode,
            'retry_count' => $webhookLog->retry_count,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log webhook retry.
     */
    public function logRetry(WebhookLog $webhookLog): void
    {
        Log::channel('single')->info('Webhook retry attempt', [
            'webhook_id' => $webhookLog->id,
            'source' => $webhookLog->source,
            'event' => $webhookLog->event,
            'retry_count' => $webhookLog->retry_count,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
