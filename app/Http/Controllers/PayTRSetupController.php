<?php

namespace App\Http\Controllers;

use App\Models\HLAccount;
use App\Logging\UserActionLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PayTRSetupController extends Controller
{
    public function __construct(
        protected UserActionLogger $userActionLogger
    ) {
    }

    /**
     * Show PayTR setup form
     */
    public function showSetup(Request $request): \Illuminate\View\View
    {
        // Try to get location_id from multiple sources
        $locationId = $this->extractLocationId($request);

        if (!$locationId) {
            abort(400, 'Missing location_id parameter. Please access this page through HighLevel or provide location_id in the URL.');
        }

        $account = HLAccount::where('location_id', $locationId)->first();

        if (!$account) {
            abort(404, 'Account not found for location: ' . $locationId);
        }

        return view('paytr.setup', [
            'account' => $account,
            'locationId' => $locationId,
            'isConfigured' => $account->hasPayTRCredentials(),
        ]);
    }

    /**
     * Extract location_id from request (query param, session, or auth token)
     */
    protected function extractLocationId(Request $request): ?string
    {
        // 1. Try from query parameter (most common)
        if ($request->has('location_id')) {
            return $request->get('location_id');
        }

        // 2. Try from session (set during OAuth)
        if ($request->session()->has('location_id')) {
            return $request->session()->get('location_id');
        }

        // 3. Try to decode from HighLevel auth_token (if provided in iframe)
        if ($request->has('auth_token')) {
            try {
                $token = $request->get('auth_token');
                $decoded = $this->decodeHighLevelToken($token);
                if (isset($decoded['locationId'])) {
                    return $decoded['locationId'];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to decode auth_token', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Decode HighLevel auth token to get location info
     */
    protected function decodeHighLevelToken(string $token): ?array
    {
        // HighLevel auth tokens are encrypted with SSO key
        // This is a placeholder - implement based on HighLevel's SSO documentation
        $ssoKey = config('services.highlevel.sso_key');

        if (!$ssoKey) {
            return null;
        }

        // Decrypt token using SSO key (implementation depends on HL's encryption method)
        // For now, we'll just try to decode as JWT
        try {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode($parts[1]), true);
                return $payload;
            }
        } catch (\Exception $e) {
            // Token decode failed
        }

        return null;
    }

    /**
     * Save PayTR credentials
     */
    public function saveCredentials(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|string',
            'merchant_id' => 'required|string',
            'merchant_key' => 'required|string',
            'merchant_salt' => 'required|string',
            'test_mode' => 'boolean',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }


        try {
            $account = HLAccount::where('location_id', $request->location_id)->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found',
                ], 404);
            }

            //            // Test PayTR credentials before saving
//            $testResult = $this->testPayTRCredentials([
//                'merchant_id' => $request->merchant_id,
//                'merchant_key' => $request->merchant_key,
//                'merchant_salt' => $request->merchant_salt,
//                'test_mode' => $request->boolean('test_mode', true),
//            ]);
//
//            if (!$testResult['success']) {
//                return response()->json([
//                    'success' => false,
//                    'message' => 'PayTR credentials test failed: ' . $testResult['error'],
//                ], 400);
//            }

            // Save credentials
            $account->setPayTRCredentials([
                'merchant_id' => $request->merchant_id,
                'merchant_key' => $request->merchant_key,
                'merchant_salt' => $request->merchant_salt,
                'test_mode' => $request->boolean('test_mode', true),
            ]);

            $this->userActionLogger->log($account, 'paytr_configured', [
                'merchant_id' => $request->merchant_id,
                'test_mode' => $request->boolean('test_mode', true),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PayTR credentials saved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('PayTR credentials save failed', [
                'location_id' => $request->location_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save credentials',
            ], 500);
        }
    }

    /**
     * Test PayTR credentials by making a test API call
     */
    public function testCredentials(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|string',
            'merchant_key' => 'required|string',
            'merchant_salt' => 'required|string',
            'test_mode' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $result = $this->testPayTRCredentials([
            'merchant_id' => $request->merchant_id,
            'merchant_key' => $request->merchant_key,
            'merchant_salt' => $request->merchant_salt,
            'test_mode' => $request->boolean('test_mode', true),
        ]);

        return response()->json($result);
    }

    /**
     * Show current PayTR configuration
     */
    public function showConfiguration(Request $request): JsonResponse
    {
        $locationId = $request->get('location_id');

        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing location_id parameter',
            ], 400);
        }

        $account = HLAccount::where('location_id', $locationId)->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'configured' => $account->hasPayTRCredentials(),
            'merchant_id' => $account->paytr_merchant_id,
            'test_mode' => $account->paytr_test_mode,
            'configured_at' => $account->paytr_configured_at?->toIso8601String(),
        ]);
    }

    /**
     * Remove PayTR configuration
     */
    public function removeConfiguration(Request $request): JsonResponse
    {
        $locationId = $request->get('location_id');

        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing location_id parameter',
            ], 400);
        }

        try {
            $account = HLAccount::where('location_id', $locationId)->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found',
                ], 404);
            }

            // Clear PayTR credentials
            $account->update([
                'paytr_merchant_id' => null,
                'paytr_merchant_key' => null,
                'paytr_merchant_salt' => null,
                'paytr_configured' => false,
                'paytr_configured_at' => null,
            ]);

            $this->userActionLogger->log($account, 'paytr_unconfigured');

            return response()->json([
                'success' => true,
                'message' => 'PayTR configuration removed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('PayTR configuration removal failed', [
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove configuration',
            ], 500);
        }
    }

    /**
     * Test PayTR credentials by making a test request
     */
    protected function testPayTRCredentials(array $credentials): array
    {
        try {
            // Test with a minimal payment token request
            $merchantId = $credentials['merchant_id'];
            $merchantKey = $credentials['merchant_key'];
            $merchantSalt = $credentials['merchant_salt'];
            $testMode = $credentials['test_mode'] ? '1' : '0';

            // Create test data
            $userIp = request()->ip();
            $merchantOid = 'TEST_' . time();
            $email = 'test@example.com';
            $paymentAmount = '100'; // 1 TRY in kuruş
            $userBasket = base64_encode('Test item');
            $noInstallment = '1';
            $maxInstallment = '1';
            $currency = 'TL';

            // Generate hash
            $hashStr = $merchantId . $userIp . $merchantOid . $email .
                $paymentAmount . $userBasket . $noInstallment .
                $maxInstallment . $currency . $testMode . $merchantSalt;

            $paytrToken = base64_encode(hash_hmac('sha256', $hashStr, $merchantKey, true));
            // Test request to PayTR
            $response = Http::timeout(10)->post('https://www.paytr.com/odeme/api/get-token', [
                'merchant_id' => $merchantId,
                'user_ip' => $userIp,
                'merchant_oid' => $merchantOid,
                'email' => $email,
                'payment_amount' => $paymentAmount,
                'paytr_token' => $paytrToken,
                'user_basket' => $userBasket,
                'debug_on' => '1',
                'no_installment' => $noInstallment,
                'max_installment' => $maxInstallment,
                'user_name' => 'Test User',
                'user_address' => 'Test Address',
                'user_phone' => '5551234567',
                'merchant_ok_url' => config('app.url') . '/payments/success',
                'merchant_fail_url' => config('app.url') . '/payments/error',
                'timeout_limit' => '30',
                'currency' => $currency,
                'test_mode' => $testMode,
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'PayTR API request failed: HTTP ' . $response->status(),
                ];
            }

            $responseData = $response->json();

            if (!isset($responseData['status']) || $responseData['status'] !== 'success') {
                return [
                    'success' => false,
                    'error' => $responseData['reason'] ?? 'Unknown PayTR error',
                ];
            }

            return [
                'success' => true,
                'message' => 'PayTR credentials are valid',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }
}
