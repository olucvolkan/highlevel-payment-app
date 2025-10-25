<?php

namespace App\Logging;

use App\Models\HLAccount;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Log;

class UserActionLogger
{
    /**
     * Log user action.
     */
    public function log(HLAccount $account, string $action, array $data = []): UserActivityLog
    {
        $activityLog = UserActivityLog::create([
            'hl_account_id' => $account->id,
            'location_id' => $account->location_id,
            'user_id' => $account->user_id,
            'action' => $action,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'description' => $data['description'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => array_merge($data, ['account_id' => $account->id]),
        ]);

        Log::channel('single')->info('User action logged', [
            'log_id' => $activityLog->id,
            'action' => $action,
            'location_id' => $activityLog->location_id,
            'user_id' => $activityLog->user_id,
            'entity_type' => $activityLog->entity_type,
            'entity_id' => $activityLog->entity_id,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $activityLog;
    }

    /**
     * Log account creation.
     */
    public function logAccountCreated(HLAccount $account): UserActivityLog
    {
        return $this->log('account.created', [
            'hl_account_id' => $account->id,
            'location_id' => $account->location_id,
            'entity_type' => 'HLAccount',
            'entity_id' => $account->id,
            'description' => 'HighLevel account created',
            'metadata' => [
                'company_id' => $account->company_id,
            ],
        ]);
    }

    /**
     * Log OAuth callback.
     */
    public function logOAuthCallback(string $locationId, bool $success = true): UserActivityLog
    {
        return $this->log('oauth.callback', [
            'location_id' => $locationId,
            'description' => $success ? 'OAuth callback successful' : 'OAuth callback failed',
            'metadata' => [
                'success' => $success,
            ],
        ]);
    }

    /**
     * Log payment creation.
     */
    public function logPaymentCreated(int $paymentId, string $locationId): UserActivityLog
    {
        return $this->log('payment.created', [
            'location_id' => $locationId,
            'entity_type' => 'Payment',
            'entity_id' => $paymentId,
            'description' => 'Payment created',
        ]);
    }

    /**
     * Log payment status update.
     */
    public function logPaymentStatusUpdated(int $paymentId, string $oldStatus, string $newStatus, string $locationId): UserActivityLog
    {
        return $this->log('payment.status_updated', [
            'location_id' => $locationId,
            'entity_type' => 'Payment',
            'entity_id' => $paymentId,
            'description' => "Payment status updated from {$oldStatus} to {$newStatus}",
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
        ]);
    }

    /**
     * Log refund processed.
     */
    public function logRefundProcessed(int $paymentId, float $amount, string $locationId): UserActivityLog
    {
        return $this->log('refund.processed', [
            'location_id' => $locationId,
            'entity_type' => 'Payment',
            'entity_id' => $paymentId,
            'description' => "Refund processed for amount: {$amount}",
            'metadata' => [
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * Log configuration updated.
     */
    public function logConfigUpdated(HLAccount $account, string $mode): UserActivityLog
    {
        return $this->log('config.updated', [
            'hl_account_id' => $account->id,
            'location_id' => $account->location_id,
            'entity_type' => 'HLAccount',
            'entity_id' => $account->id,
            'description' => "Configuration updated for {$mode} mode",
            'metadata' => [
                'mode' => $mode,
            ],
        ]);
    }

    /**
     * Log integration created.
     */
    public function logIntegrationCreated(HLAccount $account): UserActivityLog
    {
        return $this->log('integration.created', [
            'hl_account_id' => $account->id,
            'location_id' => $account->location_id,
            'entity_type' => 'HLAccount',
            'entity_id' => $account->id,
            'description' => 'Payment integration created in HighLevel',
            'metadata' => [
                'integration_id' => $account->integration_id,
            ],
        ]);
    }

    /**
     * Log payment method added.
     */
    public function logPaymentMethodAdded(int $paymentMethodId, string $locationId, string $contactId): UserActivityLog
    {
        return $this->log('payment_method.added', [
            'location_id' => $locationId,
            'entity_type' => 'PaymentMethod',
            'entity_id' => $paymentMethodId,
            'description' => 'Payment method added',
            'metadata' => [
                'contact_id' => $contactId,
            ],
        ]);
    }

    /**
     * Log payment method deleted.
     */
    public function logPaymentMethodDeleted(int $paymentMethodId, string $locationId, string $contactId): UserActivityLog
    {
        return $this->log('payment_method.deleted', [
            'location_id' => $locationId,
            'entity_type' => 'PaymentMethod',
            'entity_id' => $paymentMethodId,
            'description' => 'Payment method deleted',
            'metadata' => [
                'contact_id' => $contactId,
            ],
        ]);
    }
}
