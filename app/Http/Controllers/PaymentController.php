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
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
     */
    public function query(Request $request): Response
    {
        try {
            $account = $this->getAccountFromRequest($request);
            
            if (!$account) {
                return response(['error' => 'Invalid account'], 401);
            }

            $data = $request->all();
            $type = $data['type'] ?? null;

            $this->userActionLogger->log($account, 'payment_query', $data);

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
                    return response(['error' => 'Invalid operation type'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Payment query failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Display payment iframe page
     */
    public function paymentPage(Request $request): \Illuminate\View\View
    {
        $account = $this->getAccountFromRequest($request);
        
        if (!$account) {
            abort(401, 'Invalid account');
        }

        $data = $request->all();

        // Validate required parameters
        $validator = Validator::make($data, [
            'amount' => 'required|numeric|min:0.01',
            'email' => 'required|email',
            'transactionId' => 'required|string',
        ]);

        if ($validator->fails()) {
            abort(400, 'Invalid payment parameters');
        }

        // Create payment
        $result = $this->paymentService->createPayment($account, $data);

        if (!$result['success']) {
            abort(400, $result['error']);
        }

        return view('payments.iframe', [
            'iframeUrl' => $result['iframe_url'],
            'merchantOid' => $result['merchant_oid'],
            'transactionId' => $data['transactionId'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'TRY',
        ]);
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
    protected function handleVerifyPayment(array $data): Response
    {
        $transactionId = $data['transactionId'] ?? null;
        $chargeId = $data['chargeId'] ?? null;

        if (!$transactionId) {
            return response(['error' => 'Transaction ID required'], 400);
        }

        $result = $this->paymentService->verifyPayment($transactionId, $chargeId);

        return response($result);
    }

    /**
     * Handle list payment methods operation
     */
    protected function handleListPaymentMethods(HLAccount $account, array $data): Response
    {
        $contactId = $data['contactId'] ?? null;
        $utoken = $data['utoken'] ?? null;

        if (!$contactId) {
            return response(['error' => 'Contact ID required'], 400);
        }

        try {
            $provider = PaymentProviderFactory::default();
            
            $methods = $provider->listPaymentMethods(
                $account->location_id,
                $contactId,
                $utoken
            );

            return response(['paymentMethods' => $methods]);
        } catch (\Exception $e) {
            Log::error('List payment methods failed', [
                'account_id' => $account->id,
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return response(['error' => 'Failed to fetch payment methods'], 500);
        }
    }

    /**
     * Handle charge payment operation
     */
    protected function handleChargePayment(HLAccount $account, array $data): Response
    {
        $validator = Validator::make($data, [
            'amount' => 'required|numeric|min:0.01',
            'paymentMethodId' => 'required|string',
            'transactionId' => 'required|string',
            'contactId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response(['error' => 'Invalid parameters'], 400);
        }

        try {
            $paymentMethod = PaymentMethod::where('id', $data['paymentMethodId'])
                ->where('hl_account_id', $account->id)
                ->where('contact_id', $data['contactId'])
                ->first();

            if (!$paymentMethod) {
                return response(['error' => 'Payment method not found'], 404);
            }

            $provider = PaymentProviderFactory::make($paymentMethod->provider);

            $result = $provider->chargePaymentMethod($paymentMethod, [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'TRY',
                'email' => $data['email'] ?? 'customer@example.com',
                'merchant_oid' => 'CHARGE_' . time() . '_' . rand(1000, 9999),
                'transaction_id' => $data['transactionId'],
            ]);

            if ($result['success']) {
                return response(['success' => true, 'chargeId' => $result['charge_id'] ?? null]);
            }

            return response(['error' => $result['error'] ?? 'Charge failed'], 400);
        } catch (\Exception $e) {
            Log::error('Charge payment failed', [
                'account_id' => $account->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            return response(['error' => 'Charge processing failed'], 500);
        }
    }

    /**
     * Handle create subscription operation
     */
    protected function handleCreateSubscription(HLAccount $account, array $data): Response
    {
        // For now, return not implemented
        return response(['error' => 'Subscriptions not yet implemented'], 501);
    }

    /**
     * Handle refund operation
     */
    protected function handleRefund(HLAccount $account, array $data): Response
    {
        $validator = Validator::make($data, [
            'chargeId' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response(['error' => 'Invalid parameters'], 400);
        }

        try {
            $payment = Payment::where('charge_id', $data['chargeId'])
                ->where('hl_account_id', $account->id)
                ->first();

            if (!$payment) {
                return response(['error' => 'Payment not found'], 404);
            }

            $result = $this->paymentService->processRefund($payment, $data['amount']);

            if ($result['success']) {
                return response(['success' => true]);
            }

            return response(['error' => $result['message']], 400);
        } catch (\Exception $e) {
            Log::error('Refund failed', [
                'account_id' => $account->id,
                'charge_id' => $data['chargeId'],
                'error' => $e->getMessage(),
            ]);

            return response(['error' => 'Refund processing failed'], 500);
        }
    }

    /**
     * Check payment status (used by iframe for polling)
     */
    public function status(Request $request): Response
    {
        $merchantOid = $request->get('merchantOid');
        $transactionId = $request->get('transactionId');

        if (!$merchantOid && !$transactionId) {
            return response(['error' => 'Missing payment identifier'], 400);
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
                return response(['status' => 'not_found'], 404);
            }

            switch ($payment->status) {
                case Payment::STATUS_SUCCESS:
                    return response([
                        'status' => 'success',
                        'chargeId' => $payment->charge_id ?: $payment->merchant_oid,
                        'transactionId' => $payment->transaction_id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'paidAt' => $payment->paid_at?->toIso8601String(),
                    ]);

                case Payment::STATUS_FAILED:
                    return response([
                        'status' => 'failed',
                        'error' => $payment->error_message ?: 'Payment failed',
                        'transactionId' => $payment->transaction_id,
                    ]);

                default:
                    return response([
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

            return response(['error' => 'Status check failed'], 500);
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
