<?php

namespace App\Http\Controllers;

use App\Models\HLAccount;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use App\Services\HighLevelService;
use App\PaymentGateways\PaymentProviderFactory;
use App\Logging\PaymentLogger;
use App\Logging\UserActionLogger;
use App\Http\Requests\InitializePaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected HighLevelService $highLevelService,
        protected PaymentLogger $paymentLogger,
        protected UserActionLogger $userActionLogger
    ) {
    }

    /**
     * HighLevel query endpoint for payment verification and operations
     *
     * SECURITY: Validates that API key belongs to the requesting location
     */
    public function query(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $locationId = $request->header('X-Location-Id') ?: $data['locationId'] ?? null;
            $apiKey = $data['apiKey'] ?? null;

            // Validate required parameters
            if (!$locationId || !$apiKey) {
                Log::warning('Payment query missing credentials', [
                    'has_location_id' => !empty($locationId),
                    'has_api_key' => !empty($apiKey),
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Missing credentials'], 400);
            }

            // SECURITY FIX: Find account by BOTH location_id AND api_key
            // This prevents cross-location attacks where an attacker uses their valid
            // API key to access another location's data
            $account = HLAccount::where('location_id', $locationId)
                ->where(function($query) use ($apiKey) {
                    $query->where('api_key_live', $apiKey)
                          ->orWhere('api_key_test', $apiKey);
                })
                ->first();

            if (!$account) {
                Log::warning('Invalid API key or location combination', [
                    'location_id' => $locationId,
                    'api_key_prefix' => substr($apiKey, 0, 8) . '...',
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Unauthorized - Invalid credentials'], 401);
            }

            $type = $data['type'] ?? null;

            $this->userActionLogger->log($account, 'payment_query', array_merge($data, ['api_key_valid' => true]));

            switch ($type) {
                case 'verify':
                    return $this->handleVerifyPayment($data);

                case 'list_payment_methods':
                    return $this->handleListPaymentMethods($account, $data);

                case 'charge_payment':
                    return $this->handleChargePayment($account, $data);

                case 'create_subscription':
                    return $this->handleCreateSubscription($account, $data);

                case 'refund':
                    return $this->handleRefund($account, $data);

                default:
                    return response()->json(['error' => 'Invalid operation type'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Payment query failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Display payment iframe page
     *
     * This endpoint is loaded by HighLevel in an iframe WITHOUT payment parameters.
     * The iframe will receive payment details via postMessage from HighLevel and then
     * call the /api/payments/initialize endpoint to create the PayTR payment.
     */
    public function paymentPage(Request $request): \Illuminate\View\View
    {
        $account = $this->getAccountFromRequest($request);

        if (!$account) {
            abort(401, 'Invalid account');
        }

        // Check if PayTR credentials are configured
        if (!$account->hasPayTRCredentials()) {
            return view('payments.setup-required', [
                'locationId' => $account->location_id,
                'setupUrl' => route('paytr.setup', ['location_id' => $account->location_id]),
            ]);
        }

        // Return iframe that waits for payment_initiate_props from HighLevel
        return view('payments.iframe', [
            'locationId' => $account->location_id,
            'apiUrl' => config('app.url'),
        ]);
    }

    /**
     * Initialize payment after receiving payment details from HighLevel via postMessage
     *
     * This endpoint is called by JavaScript in the payment iframe after it receives
     * payment_initiate_props event from HighLevel parent window.
     */
    public function initialize(InitializePaymentRequest $request): JsonResponse
    {
        $account = $this->getAccountFromRequest($request);

        if (!$account) {
            return response()->json(['error' => 'Invalid account'], 401);
        }

        // Validation is automatically handled by InitializePaymentRequest
        $data = $request->validated();

        try {
            // Create PayTR payment
            $result = $this->paymentService->createPayment($account, $data);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Log payment initialization
            $this->userActionLogger->log($account, 'payment_initialized', [
                'transaction_id' => $data['transactionId'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'TRY',
            ]);

            return response()->json([
                'success' => true,
                'iframe_url' => $result['iframe_url'],
                'merchant_oid' => $result['merchant_oid'],
                'token' => $result['token'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment initialization failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Handle PayTR payment callback
     */
    public function callback(Request $request): Response
    {
        $callbackData = $request->all();

        $this->paymentLogger->logCallback('paytr', $callbackData);

        try {
            $success = $this->paymentService->processCallback($callbackData);

            if ($success) {
                return response('OK');
            }

            return response('FAILED', 400);
        } catch (\Exception $e) {
            Log::error('PayTR callback processing failed', [
                'error' => $e->getMessage(),
                'data' => $callbackData,
            ]);

            return response('ERROR', 500);
        }
    }

    /**
     * Handle payment success (redirect)
     */
    public function success(Request $request): \Illuminate\View\View
    {
        $merchantOid = $request->get('merchant_oid');
        $payment = null;

        if ($merchantOid) {
            $payment = Payment::where('merchant_oid', $merchantOid)->first();
        }

        return view('payments.success', [
            'payment' => $payment,
            'merchantOid' => $merchantOid,
        ]);
    }

    /**
     * Handle payment error (redirect)
     */
    public function error(Request $request): \Illuminate\View\View
    {
        $merchantOid = $request->get('merchant_oid');
        $payment = null;

        if ($merchantOid) {
            $payment = Payment::where('merchant_oid', $merchantOid)->first();
        }

        return view('payments.error', [
            'payment' => $payment,
            'merchantOid' => $merchantOid,
            'error' => $request->get('error', 'Payment failed'),
        ]);
    }

    /**
     * Handle verify payment operation
     */
    protected function handleVerifyPayment(array $data): JsonResponse
    {
        $transactionId = $data['transactionId'] ?? null;
        $chargeId = $data['chargeId'] ?? null;

        if (!$transactionId) {
            return response()->json(['error' => 'Transaction ID required'], 400);
        }

        $result = $this->paymentService->verifyPayment($transactionId, $chargeId);

        return response()->json($result);
    }

    /**
     * Handle list payment methods operation
     */
    protected function handleListPaymentMethods(HLAccount $account, array $data): JsonResponse
    {
        $contactId = $data['contactId'] ?? null;
        $utoken = $data['utoken'] ?? null;

        if (!$contactId) {
            return response()->json(['error' => 'Contact ID required'], 400);
        }

        try {
            if (!$account->hasPayTRCredentials()) {
                return response()->json(['error' => 'PayTR not configured for this account'], 400);
            }

            $provider = PaymentProviderFactory::forAccount($account);

            $methods = $provider->listPaymentMethods(
                $account->location_id,
                $contactId,
                $utoken
            );

            return response()->json(['paymentMethods' => $methods]);
        } catch (\Exception $e) {
            Log::error('List payment methods failed', [
                'account_id' => $account->id,
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to fetch payment methods'], 500);
        }
    }

    /**
     * Handle charge payment operation
     */
    protected function handleChargePayment(HLAccount $account, array $data): JsonResponse
    {
        $validator = Validator::make($data, [
            'amount' => 'required|numeric|min:0.01',
            'paymentMethodId' => 'required|string',
            'transactionId' => 'required|string',
            'contactId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        try {
            $paymentMethod = PaymentMethod::where('id', $data['paymentMethodId'])
                ->where('hl_account_id', $account->id)
                ->where('contact_id', $data['contactId'])
                ->first();

            if (!$paymentMethod) {
                return response()->json(['error' => 'Payment method not found'], 404);
            }

            $provider = PaymentProviderFactory::forAccount($account, $paymentMethod->provider);

            $result = $provider->chargePaymentMethod($paymentMethod, [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'TRY',
                'email' => $data['email'] ?? 'customer@example.com',
                'merchant_oid' => 'CHARGE_' . time() . '_' . rand(1000, 9999),
                'transaction_id' => $data['transactionId'],
            ]);

            if ($result['success']) {
                return response()->json(['success' => true, 'chargeId' => $result['charge_id'] ?? null]);
            }

            return response()->json(['error' => $result['error'] ?? 'Charge failed'], 400);
        } catch (\Exception $e) {
            Log::error('Charge payment failed', [
                'account_id' => $account->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Charge processing failed'], 500);
        }
    }

    /**
     * Handle create subscription operation
     */
    protected function handleCreateSubscription(HLAccount $account, array $data): JsonResponse
    {
        // For now, return not implemented
        return response()->json(['error' => 'Subscriptions not yet implemented'], 501);
    }

    /**
     * Handle refund operation
     */
    protected function handleRefund(HLAccount $account, array $data): JsonResponse
    {
        $validator = Validator::make($data, [
            'chargeId' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        try {
            $payment = Payment::where('charge_id', $data['chargeId'])
                ->where('hl_account_id', $account->id)
                ->first();

            if (!$payment) {
                return response()->json(['error' => 'Payment not found'], 404);
            }

            $result = $this->paymentService->processRefund($payment, $data['amount']);

            if ($result['success']) {
                return response()->json(['success' => true]);
            }

            return response()->json(['error' => $result['message']], 400);
        } catch (\Exception $e) {
            Log::error('Refund failed', [
                'account_id' => $account->id,
                'charge_id' => $data['chargeId'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Refund processing failed'], 500);
        }
    }

    /**
     * Check payment status (used by iframe for polling)
     */
    public function status(Request $request): JsonResponse
    {
        $merchantOid = $request->get('merchantOid');
        $transactionId = $request->get('transactionId');

        if (!$merchantOid && !$transactionId) {
            return response()->json(['error' => 'Missing payment identifier'], 400);
        }

        try {
            $query = Payment::query();

            if ($merchantOid) {
                $query->where('merchant_oid', $merchantOid);
            } elseif ($transactionId) {
                $query->where('transaction_id', $transactionId);
            }

            $payment = $query->first();

            if (!$payment) {
                return response()->json(['status' => 'not_found'], 404);
            }

            switch ($payment->status) {
                case Payment::STATUS_SUCCESS:
                    return response()->json([
                        'status' => 'success',
                        'chargeId' => $payment->charge_id ?: $payment->merchant_oid,
                        'transactionId' => $payment->transaction_id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'paidAt' => $payment->paid_at?->toIso8601String(),
                    ]);

                case Payment::STATUS_FAILED:
                    return response()->json([
                        'status' => 'failed',
                        'error' => $payment->error_message ?: 'Payment failed',
                        'transactionId' => $payment->transaction_id,
                    ]);

                default:
                    return response()->json([
                        'status' => 'pending',
                        'transactionId' => $payment->transaction_id,
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Payment status check failed', [
                'merchantOid' => $merchantOid,
                'transactionId' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Status check failed'], 500);
        }
    }

    /**
     * Get HL account from request authentication
     */
    protected function getAccountFromRequest(Request $request): ?HLAccount
    {
        // In production, this would verify HighLevel's signature/token
        // For now, we'll use location_id from the request
        $locationId = $request->header('X-Location-Id') ?: $request->get('locationId');

        if (!$locationId) {
            return null;
        }

        return HLAccount::where('location_id', $locationId)->first();
    }
}
