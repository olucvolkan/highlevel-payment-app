<?php

namespace App\Services;

use App\Models\HLAccount;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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

    public function exchangeCodeForToken(string $code, string $userType = 'Company'): array
    {
        try {
            $client = new Client();

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded'
            ];

            $options = [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'user_type' => $userType,
                ],
                'headers' => $headers,
            ];

            $response = $client->post($this->oauthUrl . '/oauth/token', $options);
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('HighLevel token exchange successful', [
                'has_access_token' => isset($body['access_token']),
                'response_keys' => array_keys($body),
                'full_response' => $body, // Log full response to debug location_id issue
            ]);

            return $body;

        } catch (GuzzleException $e) {
            Log::error('HighLevel token exchange failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'error' => 'Token exchange failed: ' . $e->getMessage(),
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
            $client = new Client();

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded'
            ];

            $options = [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ],
                'headers' => $headers,
            ];

            $response = $client->post($this->oauthUrl . '/oauth/token', $options);

            $data = json_decode($response->getBody()->getContents(), true);

            $account->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
                'token_expires_at' => now()->addSeconds($data['expires_in']),
            ]);

            Log::info('HighLevel token refresh successful', [
                'account_id' => $account->id,
            ]);

            return $data;

        } catch (GuzzleException $e) {
            Log::error('HighLevel token refresh failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'error' => 'Token refresh failed: ' . $e->getMessage(),
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
