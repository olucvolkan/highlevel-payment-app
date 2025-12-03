<?php

namespace App\Http\Controllers;

use App\Models\HLAccount;
use App\Services\HighLevelService;
use App\Services\UserActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HighLevelProviderController extends Controller
{
    public function __construct(
        protected HighLevelService $highLevelService,
        protected UserActionLogger $userActionLogger
    ) {}

    /**
     * Handle HighLevel white-label provider registration request.
     * This endpoint receives requests from HighLevel when a user clicks "Connect PayTR"
     *
     * Expected request format from HighLevel:
     * {
     *   "locationId": "abc123",
     *   "companyId": "xyz987",
     *   "action": "register",
     *   "providerKey": "paytr",
     *   "callbackUrl": "https://services.leadconnectorhq.com/payments/callback/..."
     * }
     */
    public function register(Request $request)
    {
        try {
            Log::info('HighLevel provider registration request received', [
                'payload' => $request->all(),
                'ip' => $request->ip(),
            ]);

            // Validate incoming request
            $validator = Validator::make($request->all(), [
                'locationId' => 'required|string',
                'companyId' => 'nullable|string',
                'action' => 'required|string|in:register',
                'providerKey' => 'required|string|in:paytr',
                'callbackUrl' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                Log::error('HighLevel provider registration validation failed', [
                    'errors' => $validator->errors()->toArray(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid request format',
                    'details' => $validator->errors(),
                ], 400);
            }

            $locationId = $request->input('locationId');
            $companyId = $request->input('companyId');
            $callbackUrl = $request->input('callbackUrl');

            // Check if account already exists
            $account = HLAccount::where('location_id', $locationId)->first();

            if (!$account) {
                Log::warning('HighLevel provider registration for unknown location', [
                    'location_id' => $locationId,
                    'company_id' => $companyId,
                ]);

                // Create placeholder account
                $account = HLAccount::create([
                    'location_id' => $locationId,
                    'company_id' => $companyId,
                    'is_active' => false,
                ]);
            }

            // Store callback URL if provided
            if ($callbackUrl) {
                $account->update(['provider_callback_url' => $callbackUrl]);
            }

            // Generate config URL where user will enter PayTR credentials
            $configUrl = config('app.url') . '/paytr/connect?' . http_build_query([
                'locationId' => $locationId,
                'companyId' => $companyId,
            ]);

            $this->userActionLogger->log($account, 'provider_registration_requested', [
                'location_id' => $locationId,
                'company_id' => $companyId,
                'config_url' => $configUrl,
            ]);

            Log::info('HighLevel provider registration successful', [
                'location_id' => $locationId,
                'config_url' => $configUrl,
            ]);

            return response()->json([
                'success' => true,
                'configUrl' => $configUrl,
                'message' => 'Please configure PayTR credentials',
            ]);

        } catch (\Exception $e) {
            Log::error('HighLevel provider registration exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Provider registration failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show PayTR credential configuration page.
     * This is where users will enter their PayTR merchant credentials.
     */
    public function connect(Request $request)
    {
        $locationId = $request->query('locationId');
        $companyId = $request->query('companyId');

        if (!$locationId) {
            return redirect()->route('oauth.error')
                ->with('error', 'Location ID missing');
        }

        $account = HLAccount::where('location_id', $locationId)->first();

        if (!$account) {
            return redirect()->route('oauth.error')
                ->with('error', 'Account not found');
        }

        return view('paytr.connect', [
            'locationId' => $locationId,
            'companyId' => $companyId,
            'account' => $account,
            'hasExistingCredentials' => $account->hasPayTRCredentials(),
        ]);
    }

    /**
     * Save PayTR merchant credentials.
     * Called when user submits the PayTR configuration form.
     */
    public function saveCredentials(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'location_id' => 'required|string',
                'merchant_id' => 'required|string',
                'merchant_key' => 'required|string',
                'merchant_salt' => 'required|string',
                'test_mode' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $locationId = $request->input('location_id');
            $account = HLAccount::where('location_id', $locationId)->first();

            if (!$account) {
                return back()->with('error', 'Account not found')->withInput();
            }

            // Update PayTR credentials
            $account->update([
                'paytr_merchant_id' => $request->input('merchant_id'),
                'paytr_merchant_key' => $request->input('merchant_key'),
                'paytr_merchant_salt' => $request->input('merchant_salt'),
                'paytr_test_mode' => $request->input('test_mode', true),
                'is_active' => true,
            ]);

            // Generate API keys for HighLevel config
            $apiKeys = $account->generateApiKeys();

            Log::info('API keys generated for HighLevel config', [
                'location_id' => $locationId,
                'account_id' => $account->id,
                'has_live_keys' => !empty($apiKeys['api_key_live']),
                'has_test_keys' => !empty($apiKeys['api_key_test']),
            ]);

            // Create HighLevel config with generated API keys
            $configResult = $this->highLevelService->connectConfig($account, [
                'liveMode' => [
                    'apiKey' => $apiKeys['api_key_live'],
                    'publishableKey' => $apiKeys['publishable_key_live'],
                ],
                'testMode' => [
                    'apiKey' => $apiKeys['api_key_test'],
                    'publishableKey' => $apiKeys['publishable_key_test'],
                ],
            ]);

            if (isset($configResult['error'])) {
                Log::error('HighLevel config creation failed', [
                    'location_id' => $locationId,
                    'error' => $configResult['error'],
                    'details' => $configResult['details'] ?? null,
                ]);

                return back()->with('warning',
                    'PayTR credentials saved but HighLevel config creation failed. Please contact support.'
                )->withInput();
            }

            Log::info('HighLevel config created successfully', [
                'location_id' => $locationId,
                'config_id' => $configResult['_id'] ?? $configResult['id'] ?? null,
            ]);

            // Send provider connected callback to HighLevel
            if ($account->provider_callback_url) {
                $this->highLevelService->sendProviderConnected($account);
            }

            $this->userActionLogger->log($account, 'paytr_credentials_configured', [
                'location_id' => $locationId,
                'test_mode' => $request->input('test_mode', true),
                'config_created' => true,
            ]);

            Log::info('PayTR credentials saved successfully', [
                'location_id' => $locationId,
                'account_id' => $account->id,
            ]);

            return redirect()->route('paytr.connect.success');

        } catch (\Exception $e) {
            Log::error('PayTR credentials save failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to save credentials: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show success page after PayTR configuration.
     */
    public function connectSuccess()
    {
        return view('paytr.connect-success');
    }
}
