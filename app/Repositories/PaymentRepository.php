<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentRepository
{
    /**
     * Find payment by ID.
     */
    public function find(int $id): ?Payment
    {
        return Payment::find($id);
    }

    /**
     * Find payment by merchant order ID.
     */
    public function findByMerchantOid(string $merchantOid): ?Payment
    {
        return Payment::where('merchant_oid', $merchantOid)->first();
    }

    /**
     * Find payment by transaction ID.
     */
    public function findByTransactionId(string $transactionId): ?Payment
    {
        return Payment::where('transaction_id', $transactionId)->first();
    }

    /**
     * Get payments by location ID.
     */
    public function getByLocation(string $locationId, int $limit = 50): Collection
    {
        return Payment::where('location_id', $locationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get payments by status.
     */
    public function getByStatus(string $status, int $limit = 100): Collection
    {
        return Payment::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent payments.
     */
    public function getRecent(int $hours = 24, int $limit = 100): Collection
    {
        return Payment::where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get successful payments.
     */
    public function getSuccessful(int $limit = 100): Collection
    {
        return Payment::where('status', Payment::STATUS_SUCCESS)
            ->orderBy('paid_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed payments.
     */
    public function getFailed(int $limit = 100): Collection
    {
        return Payment::where('status', Payment::STATUS_FAILED)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending payments.
     */
    public function getPending(int $limit = 100): Collection
    {
        return Payment::where('status', Payment::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get payment statistics.
     */
    public function getStatistics(string $locationId = null): array
    {
        $query = Payment::query();

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return [
            'total_payments' => $query->count(),
            'successful_payments' => (clone $query)->where('status', Payment::STATUS_SUCCESS)->count(),
            'failed_payments' => (clone $query)->where('status', Payment::STATUS_FAILED)->count(),
            'pending_payments' => (clone $query)->where('status', Payment::STATUS_PENDING)->count(),
            'total_amount' => (clone $query)->where('status', Payment::STATUS_SUCCESS)->sum('amount'),
            'average_amount' => (clone $query)->where('status', Payment::STATUS_SUCCESS)->avg('amount'),
        ];
    }

    /**
     * Get payment trends (daily).
     */
    public function getDailyTrends(int $days = 30, string $locationId = null): Collection
    {
        $query = Payment::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total_count'),
            DB::raw('SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count'),
            DB::raw('SUM(CASE WHEN status = "success" THEN amount ELSE 0 END) as total_amount')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date', 'desc');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query->get();
    }

    /**
     * Create payment.
     */
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    /**
     * Update payment.
     */
    public function update(Payment $payment, array $data): bool
    {
        return $payment->update($data);
    }

    /**
     * Delete payment.
     */
    public function delete(Payment $payment): bool
    {
        return $payment->delete();
    }
}
