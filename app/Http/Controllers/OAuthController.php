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
                Log::info('Exchanging Company token for Location token', [
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                    'token_type' => $account->token_type,
                ]);

                $exchangeResult = $this->highLevelService->exchangeCompanyTokenForLocation($account, $locationId);

                if (isset($exchangeResult['error'])) {
                    Log::error('Token exchange failed during OAuth', [
                        'account_id' => $account->id,
                        'location_id' => $locationId,
                        'error' => $exchangeResult['error'],
                    ]);

                    // This is CRITICAL - without Location token, provider creation will fail
                    return redirect()->route('oauth.error')
                        ->with('error', 'Failed to obtain Location token: ' . $exchangeResult['error']);
                }

                Log::info('Token exchange successful during OAuth', [
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                ]);

                // Refresh account to get updated token
                $account->refresh();
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
                    ->with('success', 'HighLevel integration completed! Now configure your PayTR credentials to start accepting payments.');
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
     * Extract location_id from request or token response
     */
    protected function extractLocationId(Request $request, array $tokenResponse): ?string
    {
        // Try multiple sources for location_id

        // 1. From query parameter (most common in OAuth callback)
        if ($request->has('location_id')) {
            return $request->get('location_id');
        }

        // 2. From token response - try different possible keys
        $possibleKeys = ['locationId', 'location_id', 'companyId', 'company_id'];
        foreach ($possibleKeys as $key) {
            if (isset($tokenResponse[$key])) {
                return $tokenResponse[$key];
            }
        }

        // 3. From session (set during authorize)
        if ($request->session()->has('location_id')) {
            return $request->session()->get('location_id');
        }

        // 4. Try to extract from state parameter if it contains location info
        if ($request->has('state')) {
            $state = $request->get('state');
            // Check if state contains location_id (some OAuth flows encode it)
            if (str_contains($state, 'location_')) {
                preg_match('/location_([a-zA-Z0-9]+)/', $state, $matches);
                if (!empty($matches[1])) {
                    return $matches[1];
                }
            }
        }

        return null;
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
