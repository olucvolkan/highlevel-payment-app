<?php

namespace App\Services;

use App\Models\HLAccount;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HighLevelService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $oauthUrl;
    protected string $apiUrl;

    public function __construct()
    {
        $this->clientId = config('services.highlevel.client_id');
        $this->clientSecret = config('services.highlevel.client_secret');
        $this->oauthUrl = config('services.highlevel.oauth_url');
        $this->apiUrl = config('services.highlevel.api_url');
    }

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCodeForToken(string $code): array
    {
        try {
            // HighLevel requires multipart/form-data instead of application/x-www-form-urlencoded
            $response = Http::asMultipart()
                ->attach('client_id', $this->clientId)
                ->attach('client_secret', $this->clientSecret)
                ->attach('grant_type', 'authorization_code')
                ->attach('code', $code)
                ->post($this->oauthUrl . '/oauth/token');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('HighLevel token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'error' => 'Token exchange failed',
                'body' => $response->body(),
                'status' => $response->status(),
                'details' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('HighLevel token exchange exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh access token.
     */
    public function refreshToken(HLAccount $account): array
    {
        try {
            // HighLevel requires multipart/form-data instead of application/x-www-form-urlencoded
            $response = Http::asMultipart()
                ->attach('client_id', $this->clientId)
                ->attach('client_secret', $this->clientSecret)
                ->attach('grant_type', 'refresh_token')
                ->attach('refresh_token', $account->refresh_token)
                ->post($this->oauthUrl . '/oauth/token');

            if ($response->successful()) {
                $data = $response->json();

                $account->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
                    'token_expires_at' => now()->addSeconds($data['expires_in']),
                ]);

                return $data;
            }

            return [
                'error' => 'Token refresh failed',
                'details' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('HighLevel token refresh exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Public Provider Config in HighLevel.
     */
    public function createPublicProviderConfig(HLAccount $account, array $data): array
    {
        try {
            $response = Http::withToken($account->access_token)
                ->post($this->apiUrl . '/payments/create-integration', [
                    'name' => $data['name'] ?? 'PayTR Payment Integration',
                    'description' => $data['description'] ?? 'Secure payments via PayTR',
                    'imageUrl' => $data['imageUrl'] ?? config('app.url') . '/images/paytr-logo.png',
                    'locationId' => $account->location_id,
                    'queryUrl' => config('app.url') . '/api/payments/query',
                    'paymentsUrl' => config('app.url') . '/payments/page',
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $account->update(['integration_id' => $result['_id'] ?? null]);

                return $result;
            }

            return [
                'error' => 'Failed to create integration',
                'details' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('HighLevel create integration failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Connect Config (test/live mode credentials).
     */
    public function connectConfig(HLAccount $account, array $config, string $mode = 'live'): array
    {
        try {
            $response = Http::withToken($account->access_token)
                ->post($this->apiUrl . '/payments/create-config', [
                    'locationId' => $account->location_id,
                    'apiKey' => $config['apiKey'],
                    'publishableKey' => $config['publishableKey'],
                    'liveMode' => $mode === 'live',
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $account->update(['config_id' => $result['_id'] ?? null]);

                return $result;
            }

            return [
                'error' => 'Failed to connect config',
                'details' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('HighLevel connect config failed', [
                'account_id' => $account->id,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send webhook to HighLevel.
     */
    public function sendWebhook(HLAccount $account, string $event, array $payload): bool
    {
        $webhookUrl = $this->apiUrl . '/payments/custom-provider/webhook';

        $webhookLog = WebhookLog::create([
            'type' => WebhookLog::TYPE_OUTGOING,
            'source' => WebhookLog::SOURCE_HIGHLEVEL,
            'event' => $event,
            'status' => WebhookLog::STATUS_PENDING,
            'url' => $webhookUrl,
            'payload' => $payload,
        ]);

        try {
            $response = Http::withToken($account->access_token)
                ->post($webhookUrl, array_merge($payload, [
                    'locationId' => $account->location_id,
                ]));

            $webhookLog->sent_at = now();

            if ($response->successful()) {
                $webhookLog->markAsSuccess($response->json(), $response->status());

                return true;
            }

            $webhookLog->markAsFailed(
                $response->body(),
                $response->status()
            );

            return false;
        } catch (\Exception $e) {
            Log::error('HighLevel webhook send failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            $webhookLog->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Send payment captured webhook.
     */
    public function sendPaymentCaptured(HLAccount $account, array $data): bool
    {
        return $this->sendWebhook($account, 'payment.captured', [
            'event' => 'payment.captured',
            'chargeId' => $data['chargeId'],
            'ghlTransactionId' => $data['transactionId'],
            'chargeSnapshot' => [
                'status' => 'succeeded',
                'amount' => $data['amount'],
                'chargeId' => $data['chargeId'],
                'chargedAt' => $data['chargedAt'] ?? time(),
            ],
        ]);
    }

    /**
     * Send subscription active webhook.
     */
    public function sendSubscriptionActive(HLAccount $account, array $data): bool
    {
        return $this->sendWebhook($account, 'subscription.active', [
            'event' => 'subscription.active',
            'subscriptionId' => $data['subscriptionId'],
            'ghlSubscriptionId' => $data['ghlSubscriptionId'],
            'subscriptionSnapshot' => [
                'status' => 'active',
                'nextCharge' => $data['nextCharge'] ?? null,
            ],
        ]);
    }

    /**
     * Get account by location ID.
     */
    public function getAccountByLocation(string $locationId): ?HLAccount
    {
        return HLAccount::where('location_id', $locationId)
            ->where('is_active', true)
            ->first();
    }
}
