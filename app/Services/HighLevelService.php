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
     * Exchange Company token for Location token.
     * Required for accessing location-specific endpoints like creating payment providers.
     *
     * HighLevel uses a hierarchical token system:
     * - Company tokens: Access company-level resources
     * - Location tokens: Access location-specific resources (required for custom payment providers)
     *
     * @param HLAccount $account Account with company access token
     * @param string $locationId Target location ID
     * @return array Token response or error details
     */
    public function exchangeCompanyTokenForLocation(HLAccount $account, string $locationId): array
    {
        try {
            if (!$account->access_token) {
                throw new \InvalidArgumentException('Access token is required for token exchange');
            }

            if (!$account->company_id) {
                throw new \InvalidArgumentException('Company ID is required for token exchange');
            }

            Log::info('Exchanging Company token for Location token', [
                'account_id' => $account->id,
                'company_id' => $account->company_id,
                'location_id' => $locationId,
                'current_token_type' => $account->token_type ?? 'Unknown',
            ]);

            $client = new Client();

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Version' => self::API_VERSION,
                'Authorization' => 'Bearer ' . $account->access_token,
            ];

            $options = [
                'form_params' => [
                    'companyId' => $account->company_id,
                    'locationId' => $locationId,
                ],
                'headers' => $headers,
            ];

            $response = $client->post($this->oauthUrl . '/oauth/locationToken', $options);
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('Location token exchange successful', [
                'account_id' => $account->id,
                'location_id' => $locationId,
                'token_type' => $data['userType'] ?? 'Unknown',
                'expires_in' => $data['expires_in'] ?? null,
                'has_refresh_token' => isset($data['refresh_token']),
            ]);

            // Store the original company token if not already stored
            if (!$account->company_access_token) {
                $companyToken = $account->access_token;
            } else {
                $companyToken = $account->company_access_token;
            }

            // Update account with location token
            $account->update([
                'company_access_token' => $companyToken,
                'location_access_token' => $data['access_token'],
                'location_refresh_token' => $data['refresh_token'] ?? null,
                'token_type' => 'Location',
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            return $data;

        } catch (GuzzleException $e) {
            Log::error('Location token exchange failed', [
                'account_id' => $account->id ?? null,
                'location_id' => $locationId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'error' => 'Token exchange failed: ' . $e->getMessage(),
                'status' => $e->getCode(),
            ];

        } catch (\Exception $e) {
            Log::error('Location token exchange exception', [
                'error' => $e->getMessage(),
                'account_id' => $account->id ?? null,
                'location_id' => $locationId,
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => $e->getMessage()];
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
     * IMPORTANT: Endpoint is /payments/custom-provider/connect (NOT /config)
     *
     * @param HLAccount $account
     * @param array $config Should contain 'testMode' and/or 'liveMode' keys with apiKey and publishableKey
     * @return array
     */
    public function connectConfig(HLAccount $account, array $config): array
    {
        try {
            // Build payload according to HighLevel API spec
            // Note: locationId is sent as query parameter, NOT in body
            $payload = [];

            // Support both test and live mode configuration in a single call
            if (isset($config['testMode'])) {
                $payload['test'] = [
                    'apiKey' => $config['testMode']['apiKey'],
                    'publishableKey' => $config['testMode']['publishableKey'],
                ];
            }

            if (isset($config['liveMode'])) {
                $payload['live'] = [
                    'apiKey' => $config['liveMode']['apiKey'],
                    'publishableKey' => $config['liveMode']['publishableKey'],
                ];
            }

            // Build URL with locationId as query parameter
            $url = $this->apiUrl . '/payments/custom-provider/connect?' . http_build_query([
                'locationId' => $account->location_id,
            ]);

            // IMPORTANT: /connect endpoint requires Location token, NOT Company token
            // If we only have Company token, exchange it first
            $token = $account->location_access_token ?? $account->access_token;

            if (!$account->location_access_token && $account->company_id) {
                Log::info('No location token found, attempting token exchange', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                    'token_type' => $account->token_type ?? 'Unknown',
                ]);

                $exchangeResult = $this->exchangeCompanyTokenForLocation($account, $account->location_id);

                if (isset($exchangeResult['error'])) {
                    Log::error('Token exchange failed before config creation', [
                        'error' => $exchangeResult['error'],
                    ]);
                    return [
                        'error' => 'Token exchange failed: ' . $exchangeResult['error'],
                    ];
                }

                // Reload account to get new token
                $account->refresh();
                $token = $account->location_access_token;
            }

            Log::info('Creating HighLevel config via /connect endpoint', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'token_type' => $account->token_type ?? 'Unknown',
                'using_location_token' => !empty($account->location_access_token),
                'has_test_mode' => isset($payload['test']),
                'has_live_mode' => isset($payload['live']),
                'payload_keys' => array_keys($payload),
                'full_url' => $url,
                'method' => 'POST',
            ]);

            $response = Http::withToken($token)
                ->withHeaders([
                    'Version' => self::API_VERSION,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

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
                'request_url' => $url,
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
    public function createThirdPartyProvider(HLAccount $account, array $data): array
    {
        try {
            // Validate required fields
            if (!$account->location_id) {
                throw new \InvalidArgumentException('Location ID is required for white-label provider creation');
            }

            if (!$account->access_token) {
                throw new \InvalidArgumentException('Access token is required for white-label provider creation');
            }

            // Build payload according to HighLevel third-party provider API specification
            // NOTE: locationId should be in query parameters, not in the body
            $payload = [
                'name' => $data['name'] ?? config('services.highlevel.provider.name', 'PayTR'),
                'description' => $data['description'] ?? config('services.highlevel.provider.description', 'PayTR Payment Gateway for Turkey'),
                'imageUrl' => $data['imageUrl'] ?? config('services.highlevel.provider.image_url', config('app.url') . '/images/paytr-logo.png'),
                'queryUrl' => $data['queryUrl'] ?? config('services.highlevel.provider.query_url', config('app.url') . '/api/payments/query'),
                'paymentsUrl' => $data['paymentsUrl'] ?? config('services.highlevel.provider.payments_url', config('app.url') . '/payments/page'),
                'supportsSubscriptionSchedule' => $data['supportsSubscriptionSchedule'] ?? config('services.highlevel.provider.supports_subscription', true),
            ];

            Log::info('Creating HighLevel third-party payment provider', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'provider_name' => $payload['name'],
                'query_url' => $payload['queryUrl'],
                'payments_url' => $payload['paymentsUrl'],
                'supports_subscription' => $payload['supportsSubscriptionSchedule'],
                'full_payload' => $payload,
                'endpoint' => 'https://services.leadconnectorhq.com/payments/custom-provider/provider?locationId=' . $account->location_id,
                'method' => 'POST',
                'current_token_type' => $account->token_type ?? 'Unknown',
            ]);

            // CRITICAL: This endpoint requires a Location token, not a Company token
            // If we only have a Company token, exchange it for a Location token first
            $token = $account->location_access_token;

            if (!$token) {
                Log::info('No location token found, attempting token exchange', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                    'has_company_token' => !empty($account->access_token),
                ]);

                $exchangeResult = $this->exchangeCompanyTokenForLocation($account, $account->location_id);

                if (isset($exchangeResult['error'])) {
                    throw new \Exception('Failed to exchange Company token for Location token: ' . $exchangeResult['error']);
                }

                // Reload the account to get the updated token
                $account->refresh();
                $token = $account->location_access_token;
            }

            if (!$token) {
                throw new \Exception('No valid access token available after exchange attempt');
            }

            Log::info('Using location token for provider creation', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'token_type' => $account->token_type,
            ]);

            // Build the URL with locationId as query parameter
            $url = 'https://services.leadconnectorhq.com/payments/custom-provider/provider?' . http_build_query([
                'locationId' => $account->location_id,
            ]);

            $response = Http::withToken($token)
                ->withHeaders([
                    'Version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('HighLevel third-party payment provider created successfully', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                    'provider_id' => $result['id'] ?? $result['_id'] ?? null,
                    'provider_name' => $payload['name'],
                    'status_code' => $response->status(),
                    'response_body' => $result,
                    'response_headers' => $response->headers(),
                ]);

                // Store the provider_id in the account (HighLevel may return 'id' or '_id')
                if (isset($result['id']) || isset($result['_id'])) {
                    $account->update(['third_party_provider_id' => $result['id'] ?? $result['_id']]);
                }

                return [
                    'success' => true,
                    'data' => $result,
                ];
            }

            Log::error('HighLevel third-party payment provider creation failed', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'status_code' => $response->status(),
                'response_body' => $response->json(),
                'response_headers' => $response->headers(),
                'request_payload' => $payload,
                'access_token' => $account->access_token,
                'account'=> $account,
                'request_endpoint' => $url,
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
