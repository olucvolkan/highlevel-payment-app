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
        $state = $request->get('state');
        $locationId = $request->get('location_id');

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

            // Create or update HL account
            $account = $this->createOrUpdateAccount($tokenResponse, $locationId);

            if (!$account) {
                return redirect()->route('oauth.error')
                    ->with('error', 'Failed to create account');
            }

            // Create payment integration in HighLevel
            $integrationResult = $this->highLevelService->createPublicProviderConfig($account, [
                'name' => 'PayTR Turkey Payments',
                'description' => 'Accept payments from Turkish customers using PayTR',
                'imageUrl' => config('app.url') . '/images/paytr-logo.png',
            ]);

            if (isset($integrationResult['error'])) {
                Log::error('Failed to create HighLevel integration', [
                    'account_id' => $account->id,
                    'error' => $integrationResult,
                ]);
                
                // Don't fail the OAuth process, just log the error
                // The integration can be created manually later
            }

            $this->userActionLogger->log($account, 'oauth_success', [
                'location_id' => $locationId,
                'integration_id' => $integrationResult['_id'] ?? null,
            ]);

            // Redirect to success page or configuration page
            return redirect()->route('oauth.success')
                ->with('success', 'Integration successfully installed!')
                ->with('account_id', $account->id);

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
     * Create or update HL account from token response
     */
    protected function createOrUpdateAccount(array $tokenData, ?string $locationId): ?HLAccount
    {
        // Extract user and location info from token
        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? 3600;

        // In a real implementation, you'd decode the JWT token to get user/location info
        // For now, we'll use the locationId from the request or extract from token
        if (!$locationId && isset($tokenData['location_id'])) {
            $locationId = $tokenData['location_id'];
        }

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
