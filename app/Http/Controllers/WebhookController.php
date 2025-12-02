<?php

namespace App\Http\Controllers;

use App\Models\HLAccount;
use App\Models\WebhookLog;
use App\Services\PaymentService;
use App\Services\HighLevelService;
use App\Logging\WebhookLogger;
use App\Logging\UserActionLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected HighLevelService $highLevelService,
        protected WebhookLogger $webhookLogger,
        protected UserActionLogger $userActionLogger
    ) {
    }

    /**
     * Handle PayTR payment callback webhook
     */
    public function paytrCallback(Request $request): Response
    {
        $webhookLog = $this->webhookLogger->logIncoming('paytr_callback', $request->all());

        try {
            $callbackData = $request->all();

            // Validate required PayTR callback fields
            $requiredFields = ['merchant_oid', 'status', 'total_amount', 'hash'];
            foreach ($requiredFields as $field) {
                if (!isset($callbackData[$field])) {
                    Log::error('PayTR callback missing required field', [
                        'field' => $field,
                        'data' => $callbackData,
                    ]);

                    $webhookLog->markAsFailed("Missing required field: {$field}");
                    return response('Missing required field', 400);
                }
            }

            // Process the callback
            $success = $this->paymentService->processCallback($callbackData);

            if ($success) {
                $webhookLog->markAsSuccess(['processed' => true]);

                // PayTR requires "OK" response
                return response('OK');
            }

            $webhookLog->markAsFailed('Callback processing failed');
            return response('FAILED', 400);

        } catch (\Exception $e) {
            Log::error('PayTR webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            $webhookLog->markAsFailed($e->getMessage());
            return response('ERROR', 500);
        }
    }

    /**
     * Handle HighLevel marketplace webhooks (install/uninstall)
     */
    public function marketplaceWebhook(Request $request): Response
    {
        $webhookLog = $this->webhookLogger->logIncoming('highlevel_marketplace', $request->all());

        try {
            $data = $request->all();
            $event = $data['event'] ?? null;

            if (!$event) {
                Log::error('HighLevel marketplace webhook missing event', $data);
                $webhookLog->markAsFailed('Missing event field');
                return response('Missing event', 400);
            }

            switch ($event) {
                case 'app.install':
                    return $this->handleAppInstall($data, $webhookLog);

                case 'app.uninstall':
                    return $this->handleAppUninstall($data, $webhookLog);

                case 'app.update':
                    return $this->handleAppUpdate($data, $webhookLog);

                default:
                    Log::info('Unknown HighLevel marketplace event', [
                        'event' => $event,
                        'data' => $data,
                    ]);

                    $webhookLog->markAsSuccess(['event' => 'unknown', 'action' => 'ignored']);
                    return response('OK');
            }

        } catch (\Exception $e) {
            Log::error('HighLevel marketplace webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            $webhookLog->markAsFailed($e->getMessage());
            return response('Error processing webhook', 500);
        }
    }

    /**
     * Handle incoming webhooks from HighLevel for payment events
     */
    public function highlevelPaymentWebhook(Request $request): Response
    {
        $webhookLog = $this->webhookLogger->logIncoming('highlevel_payment', $request->all());

        try {
            $data = $request->all();
            $event = $data['event'] ?? null;
            $locationId = $data['location_id'] ?? null;

            if (!$event || !$locationId) {
                Log::error('HighLevel payment webhook missing required fields', $data);
                $webhookLog->markAsFailed('Missing event or location_id');
                return response('Missing required fields', 400);
            }

            $account = $this->highLevelService->getAccountByLocation($locationId);

            if (!$account) {
                Log::error('Account not found for HighLevel webhook', [
                    'location_id' => $locationId,
                    'event' => $event,
                ]);

                $webhookLog->markAsFailed('Account not found');
                return response('Account not found', 404);
            }

            $this->userActionLogger->log($account, 'highlevel_webhook_received', [
                'event' => $event,
                'data' => $data,
            ]);

            switch ($event) {
                case 'payment.request':
                    return $this->handlePaymentRequest($account, $data, $webhookLog);

                case 'subscription.request':
                    return $this->handleSubscriptionRequest($account, $data, $webhookLog);

                default:
                    Log::info('Unknown HighLevel payment event', [
                        'event' => $event,
                        'account_id' => $account->id,
                        'data' => $data,
                    ]);

                    $webhookLog->markAsSuccess(['event' => 'unknown', 'action' => 'ignored']);
                    return response('OK');
            }

        } catch (\Exception $e) {
            Log::error('HighLevel payment webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            $webhookLog->markAsFailed($e->getMessage());
            return response('Error processing webhook', 500);
        }
    }

    /**
     * Handle app install event
     */
    protected function handleAppInstall(array $data, WebhookLog $webhookLog): Response
    {
        $locationId = $data['location_id'] ?? null;
        $companyId = $data['companyId'] ?? null;
        $userId = $data['user_id'] ?? null;

        if (!$locationId) {
            $webhookLog->markAsFailed('Missing location_id in install event');
            return response('Missing location_id', 400);
        }

        // Create account entry for this installation
        $account = HLAccount::updateOrCreate(
            ['location_id' => $locationId],
            [
                'company_id' => $companyId,
                'user_id' => $userId,
                'is_active' => true,
                'installed_at' => now(),
            ]
        );

        $this->userActionLogger->log($account, 'app_installed', $data);

        Log::info('HighLevel app installed', [
            'location_id' => $locationId,
            'account_id' => $account->id,
        ]);

        $webhookLog->markAsSuccess(['account_created' => $account->id]);
        return response('OK');
    }

    /**
     * Handle app uninstall event
     */
    protected function handleAppUninstall(array $data, WebhookLog $webhookLog): Response
    {
        $locationId = $data['location_id'] ?? null;

        if (!$locationId) {
            $webhookLog->markAsFailed('Missing location_id in uninstall event');
            return response('Missing location_id', 400);
        }

        $account = HLAccount::where('location_id', $locationId)->first();

        if ($account) {
            $this->userActionLogger->log($account, 'app_uninstalled', $data);

            // Deactivate instead of delete for audit purposes
            $account->update([
                'is_active' => false,
                'uninstalled_at' => now(),
            ]);

            Log::info('HighLevel app uninstalled', [
                'location_id' => $locationId,
                'account_id' => $account->id,
            ]);
        }

        $webhookLog->markAsSuccess(['account_deactivated' => $account?->id]);
        return response('OK');
    }

    /**
     * Handle app update event
     */
    protected function handleAppUpdate(array $data, WebhookLog $webhookLog): Response
    {
        $locationId = $data['location_id'] ?? null;

        if (!$locationId) {
            $webhookLog->markAsFailed('Missing location_id in update event');
            return response('Missing location_id', 400);
        }

        $account = HLAccount::where('location_id', $locationId)->first();

        if ($account) {
            $this->userActionLogger->log($account, 'app_updated', $data);

            Log::info('HighLevel app updated', [
                'location_id' => $locationId,
                'account_id' => $account->id,
            ]);
        }

        $webhookLog->markAsSuccess(['account_updated' => $account?->id]);
        return response('OK');
    }

    /**
     * Handle payment request from HighLevel
     */
    protected function handlePaymentRequest(HLAccount $account, array $data, WebhookLog $webhookLog): Response
    {
        // This would handle direct payment requests from HighLevel
        // For now, just log and acknowledge
        Log::info('Payment request received from HighLevel', [
            'account_id' => $account->id,
            'data' => $data,
        ]);

        $webhookLog->markAsSuccess(['action' => 'payment_request_logged']);
        return response('OK');
    }

    /**
     * Handle subscription request from HighLevel
     */
    protected function handleSubscriptionRequest(HLAccount $account, array $data, WebhookLog $webhookLog): Response
    {
        // This would handle subscription requests from HighLevel
        // For now, just log and acknowledge
        Log::info('Subscription request received from HighLevel', [
            'account_id' => $account->id,
            'data' => $data,
        ]);

        $webhookLog->markAsSuccess(['action' => 'subscription_request_logged']);
        return response('OK');
    }
}
