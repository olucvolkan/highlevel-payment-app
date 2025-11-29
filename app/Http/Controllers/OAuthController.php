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

            // Register white-label payment provider in HighLevel marketplace
            $whitelabelResult = $this->highLevelService->createWhiteLabelProvider($account, [
                'name' => config('services.highlevel.whitelabel.title', 'PayTR'),
                'description' => config('services.highlevel.whitelabel.description', 'PayTR Payment Gateway for Turkey'),
                'paymentsUrl' => config('app.url') . '/payments/page',
                'queryUrl' => config('app.url') . '/api/payments/query',
                'imageUrl' => config('services.highlevel.whitelabel.image_url', config('app.url') . '/images/paytr-logo.png'),
                'supportsSubscriptionSchedule' => true,
            ]);

            if (!isset($whitelabelResult['success']) || !$whitelabelResult['success']) {
                Log::warning('Failed to register white-label provider', [
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                    'error' => $whitelabelResult['error'] ?? 'Unknown error',
                ]);

                // Don't fail the OAuth process - white-label registration is optional
                // The provider can be registered manually later if needed
            } else {
                Log::info('White-label provider registered successfully', [
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                    'provider_id' => $whitelabelResult['data']['id'] ?? null,
                ]);
            }

            $this->userActionLogger->log($account, 'oauth_success', [
                'location_id' => $locationId,
                'whitelabel_provider_id' => $whitelabelResult['data']['id'] ?? null,
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

        if (!$locationId) {
            Log::error('No location ID available in OAuth response', $tokenData);
            return null;
        }

        // Create or update account
        $account = HLAccount::updateOrCreate(
            ['location_id' => $locationId],
            [
                'user_id' => $tokenData['user_id'] ?? null,
                'company_id' => $tokenData['company_id'] ?? null,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds($expiresIn),
                'scopes' => $tokenData['scope'] ?? null,
                'is_active' => true,
            ]
        );

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
