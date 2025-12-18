<?php

namespace App\Http\Controllers;

use App\Models\HLAccount;
use App\Services\HighLevelService;
use App\Logging\UserActionLogger;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    public function __construct(
        protected HighLevelService $highLevelService,
        protected UserActionLogger $userActionLogger
    ) {
    }

    /**
     * Handle OAuth callback from HighLevel
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->get('code');

        Log::info('[DEBUG] OAuth callback started', [
            'has_code' => !empty($code),
            'code_length' => strlen($code ?? ''),
            'request_params' => array_keys($request->all()),
            'location_id_in_query' => $request->get('location_id'),
            'state' => $request->get('state'),
            'all_query_params' => $request->all(),
        ]);

        if (!$code) {
            Log::error('OAuth callback missing authorization code', $request->all());

            return redirect()->route('oauth.error')
                ->with('error', 'Authorization code missing');
        }

        try {
            // Exchange code for token
            $tokenResponse = $this->highLevelService->exchangeCodeForToken($code);

            if (isset($tokenResponse['error'])) {
                Log::error('OAuth token exchange failed', $tokenResponse);

                return redirect()->route('oauth.error')
                    ->with('error', 'Token exchange failed: ' . $tokenResponse['error']);
            }

            // Extract location_id from multiple sources
            $locationId = $this->extractLocationId($request, $tokenResponse);

            if (!$locationId) {
                Log::error('OAuth callback missing location_id', [
                    'request' => $request->all(),
                    'token_response' => $tokenResponse,
                ]);

                return redirect()->route('oauth.error')
                    ->with('error', 'Location ID not found in OAuth response');
            }

            // Add locationId to token response for account creation
            $tokenResponse['locationId'] = $locationId;

            // Create or update HL account
            $account = $this->createOrUpdateAccount($tokenResponse);

            if (!$account) {
                return redirect()->route('oauth.error')
                    ->with('error', 'Failed to create account');
            }

            // IMPORTANT: Even though we request user_type='Location', HighLevel may still return Company token
            // We need to exchange Company token for Location token if needed
            if ($account->needsLocationTokenExchange()) {
                Log::info('[DEBUG] Account needs location token exchange', [
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                    'token_type' => $account->token_type,
                    'has_access_token' => !empty($account->access_token),
                    'has_location_token' => !empty($account->location_access_token),
                ]);

                $exchangeResult = $this->highLevelService->exchangeCompanyTokenForLocation($account, $locationId);

                if (isset($exchangeResult['error'])) {
                    // Build detailed error message for logging
                    $errorDetails = [
                        'account_id' => $account->id,
                        'location_id' => $locationId,
                        'error' => $exchangeResult['error'],
                        'technical_error' => $exchangeResult['technical_error'] ?? null,
                        'status_code' => $exchangeResult['status'] ?? null,
                        'parsed_response' => $exchangeResult['parsed_response'] ?? null,
                    ];

                    Log::error('[DEBUG] Token exchange failed during OAuth', $errorDetails);

                    // This is CRITICAL - without Location token, provider creation will fail
                    // Use the user-friendly error message from the service
                    $userError = $exchangeResult['error'];

                    // Add context if this seems like a configuration issue
                    if (isset($exchangeResult['status']) && $exchangeResult['status'] === 400) {
                        $userError .= ' This may indicate a configuration issue with your integration. Please contact support if the problem persists.';
                    }

                    return redirect()->route('oauth.error')
                        ->with('error', $userError)
                        ->with('support_reference', 'TOKEN_EXCHANGE_FAILED');
                }

                Log::info('[DEBUG] Token exchange successful during OAuth', [
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                ]);

                // Refresh account to get updated token
                $account->refresh();

                // Verify that location token was actually set
                if (empty($account->location_access_token)) {
                    Log::error('[DEBUG] Token exchange succeeded but location_access_token is still empty', [
                        'account_id' => $account->id,
                        'location_id' => $locationId,
                        'exchange_result_keys' => array_keys($exchangeResult),
                    ]);

                    return redirect()->route('oauth.error')
                        ->with('error', 'Location token was not properly saved. Please try reinstalling the integration.')
                        ->with('support_reference', 'TOKEN_NOT_SAVED');
                }
            }

            Log::info('OAuth callback completed with Location token', [
                'account_id' => $account->id,
                'location_id' => $locationId,
                'token_type' => $account->token_type,
                'has_location_token' => !empty($account->location_access_token),
            ]);

            // Register third-party payment provider in HighLevel marketplace
            // This will now use the Location token
            $providerResult = $this->highLevelService->createThirdPartyProvider($account, [
                'name' => config('services.highlevel.provider.name'),
                'description' => config('services.highlevel.provider.description'),
                'imageUrl' => config('services.highlevel.provider.image_url'),
                'queryUrl' => config('services.highlevel.provider.query_url'),
                'paymentsUrl' => config('services.highlevel.provider.payments_url'),
            ]);

            if (!isset($providerResult['success']) || !$providerResult['success']) {
                Log::warning('Failed to register third-party payment provider', [
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                    'error' => $providerResult['error'] ?? 'Unknown error',
                ]);

                // Don't fail the OAuth process - provider registration is optional
                // The provider can be registered manually later if needed
            } else {
                Log::info('Third-party payment provider registered successfully', [
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                    'provider_id' => $providerResult['data']['id'] ?? null,
                ]);
            }

            $this->userActionLogger->log($account, 'oauth_success', [
                'location_id' => $locationId,
                'provider_id' => $providerResult['data']['id'] ?? null,
            ]);

            // Store location_id in session for later use
            session(['location_id' => $locationId]);

            // Check if PayTR is already configured
            if ($account->hasPayTRCredentials()) {
                // PayTR already configured, redirect to success page
                return redirect()->route('oauth.success')
                    ->with('success', 'Integration successfully installed!')
                    ->with('account_id', $account->id)
                    ->with('location_id', $locationId);
            } else {
                // PayTR not configured, redirect to setup page
                return redirect()->route('paytr.setup', ['location_id' => $locationId])
                    ->with('success', 'Integration completed! Now configure your PayTR credentials to start accepting payments.');
            }

        } catch (\Exception $e) {
            Log::error('OAuth callback processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return redirect()->route('oauth.error')
                ->with('error', 'OAuth processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Display OAuth success page
     */
    public function success(Request $request): \Illuminate\View\View
    {
        return view('oauth.success', [
            'message' => session('success', 'Integration completed successfully!'),
        ]);
    }

    /**
     * Display OAuth error page
     */
    public function error(Request $request): \Illuminate\View\View
    {
        return view('oauth.error', [
            'error' => session('error', 'OAuth process failed'),
        ]);
    }

    /**
     * Initiate OAuth flow (for manual testing)
     */
    public function authorize(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $clientId = config('services.highlevel.client_id');
        $redirectUri = config('services.highlevel.redirect_uri');
        $scopes = implode(' ', [
            'payments/orders.readonly',
            'payments/orders.write',
            'payments/subscriptions.readonly',
            'payments/transactions.readonly',
            'payments/custom-provider.readonly',
            'payments/custom-provider.write',
            'products.readonly',
            'products/prices.readonly',
        ]);

        session(['oauth_state' => $state]);

        $authUrl = config('services.highlevel.oauth_url') . '/oauth/chooselocation?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'state' => $state,
        ]);

        return redirect($authUrl);
    }

    /**
     * Extract location_id from request or token response.
     *
     * CRITICAL: This method extracts LOCATION IDs only, NOT company IDs.
     * A company can have multiple locations, so companyId ≠ locationId.
     * Using companyId as locationId causes "Location not found" errors.
     */
    protected function extractLocationId(Request $request, array $tokenResponse): ?string
    {
        $extractedId = null;
        $source = null;

        // 1. From query parameter (most common in OAuth callback from marketplace)
        if ($request->has('location_id')) {
            $extractedId = $request->get('location_id');
            $source = 'query_parameter';
        }

        // 2. From token response - ONLY check location-specific keys
        // CRITICAL FIX: Removed 'companyId' and 'company_id' from this list
        // Company IDs are different from Location IDs and cannot be used interchangeably
        if (!$extractedId) {
            $possibleKeys = ['locationId', 'location_id'];
            foreach ($possibleKeys as $key) {
                if (isset($tokenResponse[$key]) && !empty($tokenResponse[$key])) {
                    $extractedId = $tokenResponse[$key];
                    $source = "token_response[$key]";
                    break;
                }
            }
        }

        // 3. From session (set during authorize)
        if (!$extractedId && $request->session()->has('location_id')) {
            $extractedId = $request->session()->get('location_id');
            $source = 'session';
        }

        // 4. Try to extract from state parameter if it contains location info
        if (!$extractedId && $request->has('state')) {
            $state = $request->get('state');
            // Check if state contains location_id (some OAuth flows encode it)
            if (str_contains($state, 'location_')) {
                preg_match('/location_([a-zA-Z0-9_-]+)/', $state, $matches);
                if (!empty($matches[1])) {
                    $extractedId = $matches[1];
                    $source = 'state_parameter';
                }
            }
        }

        Log::info('[DEBUG] Location ID extraction completed', [
            'extracted_id' => $extractedId ?? 'NULL',
            'source' => $source ?? 'none',
            'available_query_params' => array_keys($request->all()),
            'available_token_keys' => array_keys($tokenResponse),
            'token_response_locationId' => $tokenResponse['locationId'] ?? 'NOT_SET',
            'token_response_companyId' => $tokenResponse['companyId'] ?? 'NOT_SET',
            'query_location_id' => $request->get('location_id') ?? 'NOT_SET',
        ]);

        return $extractedId;
    }

    /**
     * Create or update HL account from token response
     */
    protected function createOrUpdateAccount(array $tokenData): ?HLAccount
    {
        // Extract user and location info from token
        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? 3600;
        $locationId = $tokenData['locationId'] ?? null;
        $userType = $tokenData['userType'] ?? 'Company';

        if (!$locationId) {
            Log::error('No location ID available in OAuth response', $tokenData);
            return null;
        }

        Log::info('Creating/updating HL account', [
            'location_id' => $locationId,
            'user_type' => $userType,
            'has_refresh_token' => !empty($refreshToken),
            'expires_in' => $expiresIn,
        ]);

        // Create or update account
        // Store the token in both access_token (for backward compatibility) and the specific token field
        $account = HLAccount::updateOrCreate(
            ['location_id' => $locationId],
            [
                'user_id' => $tokenData['userId'] ?? null,
                'company_id' => $tokenData['companyId'] ?? null,
                'access_token' => $accessToken, // Keep for backward compatibility
                'refresh_token' => $refreshToken,
                'company_access_token' => $userType === 'Company' ? $accessToken : null,
                'location_access_token' => $userType === 'Location' ? $accessToken : null,
                'location_refresh_token' => $userType === 'Location' ? $refreshToken : null,
                'token_type' => $userType,
                'token_expires_at' => now()->addSeconds($expiresIn),
                'scopes' => $tokenData['scope'] ?? null,
                'is_active' => true,
            ]
        );

        Log::info('HL account created/updated', [
            'account_id' => $account->id,
            'location_id' => $locationId,
            'token_type' => $account->token_type,
            'needs_exchange' => $account->needsLocationTokenExchange(),
        ]);

        // Generate API keys if not already present (for HighLevel payment config)
        if (!$account->hasApiKeys()) {
            Log::info('Generating API keys for new account', [
                'account_id' => $account->id,
                'location_id' => $locationId,
            ]);

            $keys = $account->generateApiKeys();

            Log::info('API keys generated successfully', [
                'account_id' => $account->id,
                'location_id' => $locationId,
                'has_live_keys' => !empty($keys['api_key_live']),
                'has_test_keys' => !empty($keys['api_key_test']),
            ]);
        }

        return $account;
    }

    /**
     * Handle app uninstall webhook from HighLevel
     */
    public function uninstall(Request $request): \Illuminate\Http\Response
    {
        $locationId = $request->get('location_id');

        if (!$locationId) {
            Log::error('Uninstall webhook missing location_id', $request->all());
            return response('Missing location_id', 400);
        }

        try {
            $account = HLAccount::where('location_id', $locationId)->first();

            if ($account) {
                $this->userActionLogger->log($account, 'oauth_uninstall', [
                    'location_id' => $locationId,
                ]);

                // Deactivate the account instead of deleting it for audit purposes
                $account->update(['is_active' => false]);

                Log::info('HighLevel app uninstalled', [
                    'location_id' => $locationId,
                    'account_id' => $account->id,
                ]);
            }

            return response('OK');
        } catch (\Exception $e) {
            Log::error('Uninstall webhook processing failed', [
                'error' => $e->getMessage(),
                'location_id' => $locationId,
            ]);

            return response('Error processing uninstall', 500);
        }
    }
}
