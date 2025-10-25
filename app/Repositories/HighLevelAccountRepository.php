<?php

namespace App\Repositories;

use App\Models\HLAccount;
use Illuminate\Database\Eloquent\Collection;

class HighLevelAccountRepository
{
    /**
     * Find account by ID.
     */
    public function find(int $id): ?HLAccount
    {
        return HLAccount::find($id);
    }

    /**
     * Find account by location ID.
     */
    public function findByLocation(string $locationId): ?HLAccount
    {
        return HLAccount::where('location_id', $locationId)->first();
    }

    /**
     * Find active account by location ID.
     */
    public function findActiveByLocation(string $locationId): ?HLAccount
    {
        return HLAccount::where('location_id', $locationId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find account by company ID.
     */
    public function findByCompany(string $companyId): Collection
    {
        return HLAccount::where('company_id', $companyId)->get();
    }

    /**
     * Get all active accounts.
     */
    public function getActive(): Collection
    {
        return HLAccount::where('is_active', true)->get();
    }

    /**
     * Get accounts with expired tokens.
     */
    public function getExpiredTokens(): Collection
    {
        return HLAccount::where('token_expires_at', '<=', now())
            ->where('is_active', true)
            ->get();
    }

    /**
     * Create account.
     */
    public function create(array $data): HLAccount
    {
        return HLAccount::create($data);
    }

    /**
     * Update account.
     */
    public function update(HLAccount $account, array $data): bool
    {
        return $account->update($data);
    }

    /**
     * Update or create account by location ID.
     */
    public function updateOrCreateByLocation(string $locationId, array $data): HLAccount
    {
        return HLAccount::updateOrCreate(
            ['location_id' => $locationId],
            $data
        );
    }

    /**
     * Activate account.
     */
    public function activate(HLAccount $account): bool
    {
        return $account->update(['is_active' => true]);
    }

    /**
     * Deactivate account.
     */
    public function deactivate(HLAccount $account): bool
    {
        return $account->update(['is_active' => false]);
    }

    /**
     * Delete account.
     */
    public function delete(HLAccount $account): bool
    {
        return $account->delete();
    }

    /**
     * Get account statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_accounts' => HLAccount::count(),
            'active_accounts' => HLAccount::where('is_active', true)->count(),
            'inactive_accounts' => HLAccount::where('is_active', false)->count(),
            'expired_tokens' => HLAccount::where('token_expires_at', '<=', now())->count(),
        ];
    }
}
