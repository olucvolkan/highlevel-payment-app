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
                'full_payload' => $payload,
                'endpoint' => $this->apiUrl . '/payments/custom-provider/config',
                'method' => 'POST',
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
                    'status_code' => $response->status(),
                    'response_body' => $result,
                    'response_headers' => $response->headers(),
                ]);

                $account->update(['config_id' => $result['_id'] ?? null]);

                return $result;
            }

            Log::error('HighLevel config creation failed', [
                'account_id' => $account->id,
                'status_code' => $response->status(),
                'response_body' => $response->json(),
                'response_headers' => $response->headers(),
                'request_payload' => $payload,
                'request_endpoint' => $this->apiUrl . '/payments/custom-provider/config',
                'error_message' => $response->json()['message'] ?? $response->json()['error'] ?? 'No error message provided',
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
     * @param array{uniqueName?: string, title?: string, provider?: array, description?: string, imageUrl?: string} $data Provider configuration
     * @return array{success: bool, data?: array, error?: string, details?: array, status?: int} Response from HighLevel API or error details
     */
    public function createWhiteLabelProvider(HLAccount $account, array $data): array
    {
        try {
            // Validate required fields
            if (!$account->location_id) {
                throw new \InvalidArgumentException('Location ID is required for white-label provider creation');
            }

            if (!$account->access_token) {
                throw new \InvalidArgumentException('Access token is required for white-label provider creation');
            }

            // Get and validate provider enum value
            $provider = $data['provider'] ?? config('services.highlevel.whitelabel.provider', 'nmi');

            // Validate provider is a valid enum value
            $validProviders = ['nmi', 'authorize-net'];
            if (!in_array($provider, $validProviders)) {
                throw new \InvalidArgumentException(
                    "Invalid provider '{$provider}'. Must be one of: " . implode(', ', $validProviders)
                );
            }

            // Build payload according to HighLevel white-label provider API specification
            $payload = [
                'altId' => $account->location_id,
                'altType' => 'location',
                'uniqueName' => $data['uniqueName'] ?? config('services.highlevel.whitelabel.unique_name', 'paytr-direct'),
                'title' => $data['title'] ?? config('services.highlevel.whitelabel.title', 'PayTR'),
                'provider' => $provider,
                'description' => $data['description'] ?? config('services.highlevel.whitelabel.description', 'PayTR Payment Gateway for Turkey'),
                'imageUrl' => $data['imageUrl'] ?? config('services.highlevel.whitelabel.image_url', config('app.url') . '/images/paytr-logo.png'),
            ];

            Log::info('Creating HighLevel white-label provider', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'unique_name' => $payload['uniqueName'],
                'title' => $payload['title'],
                'alt_type' => $payload['altType'],
                'provider' => $payload['provider'],
                'full_payload' => $payload,
                'endpoint' => 'https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel',
                'method' => 'POST',
            ]);

            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'Version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post('https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel', $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('HighLevel white-label provider created successfully', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                    'provider_id' => $result['id'] ?? $result['_id'] ?? null,
                    'unique_name' => $payload['uniqueName'],
                    'status_code' => $response->status(),
                    'response_body' => $result,
                    'response_headers' => $response->headers(),
                ]);

                // Store the provider_id in the account (HighLevel may return 'id' or '_id')
                if (isset($result['id']) || isset($result['_id'])) {
                    $account->update(['whitelabel_provider_id' => $result['id'] ?? $result['_id']]);
                }

                return [
                    'success' => true,
                    'data' => $result,
                ];
            }

            Log::error('HighLevel white-label provider creation failed', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'status_code' => $response->status(),
                'response_body' => $response->json(),
                'response_headers' => $response->headers(),
                'request_payload' => $payload,
                'request_endpoint' => 'https://services.leadconnectorhq.com/payments/integrations/provider/whitelabel',
                'error_message' => $response->json()['message'] ?? $response->json()['error'] ?? 'No error message provided',
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create white-label provider',
                'details' => $response->json(),
                'status' => $response->status(),
            ];

        } catch (\InvalidArgumentException $e) {
            Log::error('HighLevel white-label provider creation validation failed', [
                'account_id' => $account->id ?? null,
                'location_id' => $account->location_id ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];

        } catch (\Exception $e) {
            Log::error('HighLevel white-label provider creation exception', [
                'account_id' => $account->id ?? null,
                'location_id' => $account->location_id ?? 'N/A',
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
     * Send provider connected callback to HighLevel.
     * Called after user successfully configures PayTR credentials.
     *
     * @param HLAccount $account
     * @return bool
     */
    public function sendProviderConnected(HLAccount $account): bool
    {
        if (!$account->provider_callback_url) {
            Log::warning('Provider connected callback skipped - no callback URL', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
            ]);
            return false;
        }

        try {
            $payload = [
                'locationId' => $account->location_id,
                'providerKey' => 'paytr',
                'status' => 'connected',
                'credentials' => [
                    'configured' => true,
                    'test_mode' => $account->paytr_test_mode ?? true,
                ],
                'timestamp' => now()->toIso8601String(),
            ];

            Log::info('Sending provider connected callback to HighLevel', [
                'account_id' => $account->id,
                'callback_url' => $account->provider_callback_url,
                'payload' => $payload,
            ]);

            $response = Http::timeout(15)
                ->post($account->provider_callback_url, $payload);

            if ($response->successful()) {
                Log::info('Provider connected callback sent successfully', [
                    'account_id' => $account->id,
                    'status' => $response->status(),
                ]);
                return true;
            }

            Log::error('Provider connected callback failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Provider connected callback exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return false;
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
