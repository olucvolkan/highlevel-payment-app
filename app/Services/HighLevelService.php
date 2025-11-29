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
    private const API_VERSION = '2021-07-28';

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
     * Connect Config (test/live mode credentials).
     * This should be called after the user configures PayTR credentials.
     *
     * @param HLAccount $account
     * @param array $config Should contain 'testMode' and/or 'liveMode' keys with apiKey and publishableKey
     * @return array
     */
    public function connectConfig(HLAccount $account, array $config): array
    {
        try {
            $payload = [
                'locationId' => $account->location_id,
            ];

            // Support both test and live mode configuration in a single call
            if (isset($config['testMode'])) {
                $payload['testMode'] = [
                    'apiKey' => $config['testMode']['apiKey'],
                    'publishableKey' => $config['testMode']['publishableKey'],
                ];
            }

            if (isset($config['liveMode'])) {
                $payload['liveMode'] = [
                    'apiKey' => $config['liveMode']['apiKey'],
                    'publishableKey' => $config['liveMode']['publishableKey'],
                ];
            }

            Log::info('Creating HighLevel config', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'has_test_mode' => isset($config['testMode']),
                'has_live_mode' => isset($config['liveMode']),
            ]);

            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'Version' => self::API_VERSION,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '/payments/custom-provider/config', $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('HighLevel config created successfully', [
                    'account_id' => $account->id,
                    'config_id' => $result['_id'] ?? null,
                    'response' => $result,
                ]);

                $account->update(['config_id' => $result['_id'] ?? null]);

                return $result;
            }

            Log::error('HighLevel config creation failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'error' => 'Failed to connect config',
                'details' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('HighLevel connect config exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                ->withHeaders([
                    'Version' => self::API_VERSION,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
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
     * Create White-Label Payment Provider in HighLevel.
     * This registers the provider in the HighLevel marketplace.
     *
     * @param HLAccount $account The HighLevel account with valid access token
     * @param array $data Provider configuration (name, description, paymentsUrl, queryUrl, imageUrl, supportsSubscriptionSchedule)
     * @return array Response from HighLevel API or error details
     */
    public function createWhiteLabelProvider(HLAccount $account, array $data): array
    {
        try {
            $payload = [
                'name' => $data['name'] ?? config('services.highlevel.whitelabel.title', 'PayTR'),
                'description' => $data['description'] ?? config('services.highlevel.whitelabel.description', 'PayTR Payment Gateway for Turkey'),
                'paymentsUrl' => $data['paymentsUrl'] ?? config('app.url') . '/payments/page',
                'queryUrl' => $data['queryUrl'] ?? config('app.url') . '/api/payments/query',
                'imageUrl' => $data['imageUrl'] ?? config('services.highlevel.whitelabel.image_url', config('app.url') . '/images/paytr-logo.png'),
                'supportsSubscriptionSchedule' => $data['supportsSubscriptionSchedule'] ?? true,
            ];

            Log::info('Creating HighLevel white-label provider', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'name' => $payload['name'],
                'paymentsUrl' => $payload['paymentsUrl'],
                'queryUrl' => $payload['queryUrl'],
            ]);

            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'Version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post('https://services.leadconnectorhq.com/payments/custom-provider/provider', $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('HighLevel white-label provider created successfully', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                    'provider_id' => $result['id'] ?? null,
                    'name' => $payload['name'],
                ]);

                // Optionally store the provider_id in the account
                if (isset($result['id'])) {
                    $account->update(['whitelabel_provider_id' => $result['id']]);
                }

                return [
                    'success' => true,
                    'data' => $result,
                ];
            }

            Log::error('HighLevel white-label provider creation failed', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'status' => $response->status(),
                'response' => $response->json(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create white-label provider',
                'details' => $response->json(),
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('HighLevel white-label provider creation exception', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
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
