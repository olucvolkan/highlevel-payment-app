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

    public function exchangeCodeForToken(string $code, string $userType = 'Location'): array
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

            Log::info('[DEBUG] Sending initial token exchange request', [
                'url' => $this->oauthUrl . '/oauth/token',
                'requested_user_type' => $userType,
                'has_code' => !empty($code),
                'code_length' => strlen($code),
            ]);

            $response = $client->post($this->oauthUrl . '/oauth/token', $options);
            $responseBody = $response->getBody()->getContents();
            $body = json_decode($responseBody, true);

            Log::info('[DEBUG] HighLevel token exchange successful', [
                'requested_user_type' => $userType,
                'response_user_type' => $body['userType'] ?? 'Unknown',
                'has_access_token' => isset($body['access_token']),
                'has_company_id' => isset($body['companyId']),
                'has_location_id' => isset($body['locationId']),
                'company_id' => $body['companyId'] ?? 'NOT_PROVIDED',
                'location_id' => $body['locationId'] ?? 'NOT_PROVIDED',
                'response_keys' => array_keys($body),
                'full_response' => $responseBody,
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
     * IMPORTANT: HighLevel can infer the companyId from the Company access token,
     * so we only need to send the locationId in the request payload.
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

            if (empty($locationId)) {
                throw new \InvalidArgumentException('Location ID is required for token exchange');
            }

            // Validate that location_id is actually a location ID (not a company ID)
            // Location IDs in HighLevel typically start with specific prefixes
            if (!$this->isValidLocationId($locationId)) {
                Log::warning('[DEBUG] Potentially invalid location_id format', [
                    'location_id' => $locationId,
                    'location_id_length' => strlen($locationId),
                    'account_id' => $account->id,
                ]);
            }

            Log::info('[DEBUG] Exchanging Company token for Location token', [
                'account_id' => $account->id,
                'company_id' => $account->company_id ?? 'NOT_SET',
                'location_id' => $locationId,
                'current_token_type' => $account->token_type ?? 'Unknown',
                'has_access_token' => !empty($account->access_token),
                'access_token_prefix' => substr($account->access_token ?? '', 0, 20) . '...',
                'location_id_length' => strlen($locationId),
            ]);

            $client = new Client();

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Version' => self::API_VERSION,
                'Authorization' => 'Bearer ' . $account->access_token,
            ];

            // CRITICAL FIX: Only send locationId in the payload
            // HighLevel infers companyId from the Company access token
            // Sending incorrect or missing companyId causes "Location not found" errors
            $options = [
                'form_params' => [
                    'locationId' => $locationId,
                ],
                'headers' => $headers,
            ];

            Log::info('[DEBUG] Sending location token exchange request', [
                'url' => $this->oauthUrl . '/oauth/locationToken',
                'method' => 'POST',
                'location_id' => $locationId,
                'form_params' => $options['form_params'],
                'headers' => array_keys($headers),
                'company_id_in_account' => $account->company_id ?? 'NOT_SET',
            ]);

            $response = $client->post($this->oauthUrl . '/oauth/locationToken', $options);
            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            Log::info('[DEBUG] Location token exchange API response', [
                'status_code' => $response->getStatusCode(),
                'response_body' => $responseBody,
                'parsed_data' => $data,
            ]);

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

            // Prepare update data
            $updateData = [
                'company_access_token' => $companyToken,
                'location_access_token' => $data['access_token'],
                'location_refresh_token' => $data['refresh_token'] ?? null,
                'token_type' => 'Location',
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ];

            // If company_id is provided in the response and not already set, store it
            // This can happen when HighLevel returns company info in the location token response
            if (isset($data['companyId']) && empty($account->company_id)) {
                $updateData['company_id'] = $data['companyId'];
                Log::info('[DEBUG] Storing company_id from location token response', [
                    'account_id' => $account->id,
                    'company_id' => $data['companyId'],
                ]);
            }

            // Update account with location token
            $account->update($updateData);

            Log::info('[DEBUG] Account updated with location token', [
                'account_id' => $account->id,
                'location_id' => $locationId,
                'has_location_access_token' => !empty($account->location_access_token),
                'has_company_access_token' => !empty($account->company_access_token),
                'has_company_id' => !empty($account->company_id),
            ]);

            return $data;

        } catch (GuzzleException $e) {
            $responseBody = null;
            $statusCode = $e->getCode();
            $parsedResponse = null;

            // Try to extract response body from Guzzle exception
            if ($e->hasResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                $statusCode = $e->getResponse()->getStatusCode();

                // Try to parse JSON response for better error messages
                try {
                    $parsedResponse = json_decode($responseBody, true);
                } catch (\Exception $parseException) {
                    // Response is not JSON, keep as string
                }
            }

            // Build detailed error context
            $errorContext = [
                'account_id' => $account->id ?? null,
                'location_id' => $locationId,
                'company_id' => $account->company_id ?? 'NOT_SET',
                'error_message' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'parsed_response' => $parsedResponse,
                'exception_class' => get_class($e),
                'request_url' => $this->oauthUrl . '/oauth/locationToken',
                'request_payload' => [
                    'locationId' => $locationId,
                ],
                'has_access_token' => !empty($account->access_token),
                'access_token_length' => strlen($account->access_token ?? ''),
                'token_type' => $account->token_type ?? 'Unknown',
            ];

            // Check for common error patterns and provide actionable insights
            $userFriendlyError = 'Token exchange failed';
            if ($statusCode === 400) {
                if ($parsedResponse && isset($parsedResponse['message'])) {
                    if (str_contains($parsedResponse['message'], 'Location not found')) {
                        $errorContext['diagnosis'] = 'The location_id provided does not exist in HighLevel or the Company token does not have access to this location';
                        $userFriendlyError = 'Unable to access the specified location. Please ensure the integration is installed in the correct HighLevel location.';
                    } elseif (str_contains($parsedResponse['message'], 'Invalid token')) {
                        $errorContext['diagnosis'] = 'The Company access token is invalid or expired';
                        $userFriendlyError = 'Authentication token is invalid. Please reinstall the integration.';
                    }
                }
            } elseif ($statusCode === 401) {
                $errorContext['diagnosis'] = 'Unauthorized - Company token may be invalid or lacks required scopes';
                $userFriendlyError = 'Authentication failed. Please reinstall the integration.';
            } elseif ($statusCode === 403) {
                $errorContext['diagnosis'] = 'Forbidden - Company token lacks permission to access this location';
                $userFriendlyError = 'Permission denied to access this location.';
            }

            Log::error('[DEBUG] Location token exchange failed - Guzzle Exception', $errorContext);

            return [
                'error' => $userFriendlyError,
                'technical_error' => $e->getMessage(),
                'status' => $statusCode,
                'response_body' => $responseBody,
                'parsed_response' => $parsedResponse,
            ];

        } catch (\Exception $e) {
            Log::error('Location token exchange exception', [
                'error' => $e->getMessage(),
                'account_id' => $account->id ?? null,
                'location_id' => $locationId,
                'company_id' => $account->company_id ?? 'NOT_SET',
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => 'Token exchange failed due to an unexpected error',
                'technical_error' => $e->getMessage(),
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


    public function connectConfig(HLAccount $account, array $config = []): array
    {
        try {
            // Build payload according to HighLevel API spec
            // Note: locationId is sent as query parameter, NOT in body
            $payload = [];

            // Get API keys from account (generated during OAuth by generateApiKeys())
            // IMPORTANT: These keys will be RETURNED to us by HighLevel in payment_initiate_props
            // This is a "round-trip" pattern:
            //   1. WE generate keys (OAuth)
            //   2. WE send keys TO HighLevel (connectConfig)
            //   3. HighLevel stores keys
            //   4. HighLevel sends keys BACK to us (payment_initiate_props)
            //   5. WE use keys to authenticate (PaymentController::initialize)
            $accountKeys = $account->getApiKeys();

            Log::info('Preparing connectConfig with account keys', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'has_live_keys' => !empty($accountKeys['live']['apiKey']) && !empty($accountKeys['live']['publishableKey']),
                'has_test_keys' => !empty($accountKeys['test']['apiKey']) && !empty($accountKeys['test']['publishableKey']),
                'live_api_key_prefix' => !empty($accountKeys['live']['apiKey']) ? substr($accountKeys['live']['apiKey'], 0, 16) . '...' : 'N/A',
                'live_pub_key_prefix' => !empty($accountKeys['live']['publishableKey']) ? substr($accountKeys['live']['publishableKey'], 0, 16) . '...' : 'N/A',
                'test_api_key_prefix' => !empty($accountKeys['test']['apiKey']) ? substr($accountKeys['test']['apiKey'], 0, 16) . '...' : 'N/A',
                'test_pub_key_prefix' => !empty($accountKeys['test']['publishableKey']) ? substr($accountKeys['test']['publishableKey'], 0, 16) . '...' : 'N/A',
            ]);

            // Support both test and live mode configuration in a single call
            // Use provided config if available, otherwise use account's generated keys
            if (isset($config['testMode'])) {
                $payload['test'] = [
                    'apiKey' => $config['testMode']['apiKey'],
                    'publishableKey' => $config['testMode']['publishableKey'],
                ];
            } elseif (!empty($accountKeys['test'])) {
                $payload['test'] = [
                    'apiKey' => $accountKeys['test']['apiKey'],
                    'publishableKey' => $accountKeys['test']['publishableKey'],
                ];
            }

            if (isset($config['liveMode'])) {
                $payload['live'] = [
                    'apiKey' => $config['liveMode']['apiKey'],
                    'publishableKey' => $config['liveMode']['publishableKey'],
                ];
            } elseif (!empty($accountKeys['live'])) {
                $payload['live'] = [
                    'apiKey' => $accountKeys['live']['apiKey'],
                    'publishableKey' => $accountKeys['live']['publishableKey'],
                ];
            }

            // Build URL with locationId as query parameter
            $url = $this->apiUrl . '/payments/custom-provider/connect?' . http_build_query([
                'locationId' => $account->location_id,
            ]);

            // STRICT: This endpoint REQUIRES Location token
            // By the time this is called, location token should already exist from provider creation
            if (!$account->location_access_token) {
                Log::error('Location token required but not found', [
                    'account_id' => $account->id,
                    'token_type' => $account->token_type,
                    'has_company_token' => !empty($account->access_token),
                ]);

                return [
                    'error' => 'Location access token is required for config creation. ' .
                              'Please complete OAuth flow first. Current token type: ' .
                              ($account->token_type ?? 'Unknown'),
                ];
            }

            $token = $account->location_access_token;

            Log::info('Creating HighLevel config via /connect endpoint', [
                'account_id' => $account->id,
                'location_id' => $account->location_id,
                'token_type' => $account->token_type ?? 'Unknown',
                'using_location_token' => !empty($account->location_access_token),
                'has_test_mode' => isset($payload['test']),
                'has_live_mode' => isset($payload['live']),
                'payload_keys' => array_keys($payload),
                'using_account_keys' => empty($config),
                'account_has_api_keys' => $account->hasApiKeys(),
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
     * Send webhook to HighLevel with automatic token refresh.
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
            // Check if token is expired and refresh if needed
            if ($account->isTokenExpired()) {
                Log::info('Token expired, refreshing before webhook', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                    'expired_at' => $account->token_expires_at,
                ]);

                $refreshResult = $this->refreshToken($account);

                if (isset($refreshResult['error'])) {
                    Log::error('Failed to refresh token before webhook', [
                        'account_id' => $account->id,
                        'error' => $refreshResult['error'],
                    ]);
                    $webhookLog->markAsFailed('Token refresh failed: ' . $refreshResult['error']);
                    return false;
                }

                // Reload account to get fresh tokens
                $account->refresh();

                // Also refresh location token if needed
                if (empty($account->location_access_token) && $account->location_id) {
                    $this->exchangeCompanyTokenForLocation($account, $account->location_id);
                    $account->refresh();
                }
            }

            // Use location_access_token if available, fallback to access_token
            // Webhooks should preferably use location-scoped tokens
            $token = $account->location_access_token ?: $account->access_token;

            if (!$token) {
                Log::error('No access token available for webhook', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                ]);
                $webhookLog->markAsFailed('No access token available');
                return false;
            }

            $response = Http::withToken($token)
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

            // If 401 Unauthorized, token might be invalid despite expiry check
            // Attempt one token refresh retry
            if ($response->status() === 401) {
                Log::warning('Webhook returned 401, attempting token refresh', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                ]);

                $refreshResult = $this->refreshToken($account);

                if (!isset($refreshResult['error'])) {
                    $account->refresh();

                    // Retry webhook with new token
                    $retryToken = $account->location_access_token ?: $account->access_token;
                    $retryResponse = Http::withToken($retryToken)
                        ->withHeaders([
                            'Version' => self::API_VERSION,
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->post($webhookUrl, array_merge($payload, [
                            'locationId' => $account->location_id,
                        ]));

                    if ($retryResponse->successful()) {
                        $webhookLog->markAsSuccess($retryResponse->json(), $retryResponse->status());
                        return true;
                    }
                }
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
                throw new \Exception('No valid location access token available after exchange attempt');
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

    /**
     * Validate if the provided ID is a valid HighLevel location ID format.
     * Location IDs should not be confused with Company IDs.
     *
     * @param string $locationId The ID to validate
     * @return bool True if format appears valid
     */
    protected function isValidLocationId(string $locationId): bool
    {
        // Basic validation: non-empty, reasonable length, alphanumeric
        if (empty($locationId) || strlen($locationId) < 10 || strlen($locationId) > 50) {
            return false;
        }

        // HighLevel IDs are typically alphanumeric
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $locationId)) {
            return false;
        }

        return true;
    }
}
