<?php

namespace App\Console\Commands;

use App\Models\HLAccount;
use App\Services\HighLevelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshExpiringTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:refresh-expiring
                          {--minutes=10 : Refresh tokens expiring within this many minutes}
                          {--force : Force refresh even if not expiring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Proactively refresh OAuth tokens that will expire soon';

    protected HighLevelService $highLevelService;

    /**
     * Create a new command instance.
     */
    public function __construct(HighLevelService $highLevelService)
    {
        parent::__construct();
        $this->highLevelService = $highLevelService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = $this->option('minutes');
        $force = $this->option('force');

        $this->info("Checking for tokens expiring within {$minutes} minutes...");

        // Find accounts with expiring tokens
        $query = HLAccount::query()
            ->where('is_active', true)
            ->whereNotNull('refresh_token');

        if ($force) {
            $expiringAccounts = $query->get();
            $this->warn("Force mode enabled - refreshing all {$expiringAccounts->count()} accounts");
        } else {
            $expiringAccounts = $query
                ->whereNotNull('token_expires_at')
                ->where('token_expires_at', '<=', now()->addMinutes($minutes))
                ->where('token_expires_at', '>', now())
                ->get();

            $this->info("Found {$expiringAccounts->count()} accounts with expiring tokens");
        }

        if ($expiringAccounts->isEmpty()) {
            $this->info('No tokens need refreshing at this time');
            return Command::SUCCESS;
        }

        $refreshed = 0;
        $failed = 0;
        $skipped = 0;

        $this->withProgressBar($expiringAccounts, function ($account) use (&$refreshed, &$failed, &$skipped) {
            try {
                $expiresAt = $account->token_expires_at?->format('Y-m-d H:i:s') ?? 'N/A';

                // Check if refresh_token exists
                if (empty($account->refresh_token)) {
                    $this->newLine();
                    $this->warn("  Account {$account->id} (location: {$account->location_id}) has no refresh token - skipping");
                    $skipped++;
                    return;
                }

                $this->newLine();
                $this->line("  Refreshing token for account {$account->id} (location: {$account->location_id}, expires: {$expiresAt})");

                $result = $this->highLevelService->refreshToken($account);

                if (isset($result['error'])) {
                    $this->error("    ✗ Refresh failed: {$result['error']}");
                    $failed++;

                    Log::error('Scheduled token refresh failed', [
                        'account_id' => $account->id,
                        'location_id' => $account->location_id,
                        'error' => $result['error'],
                    ]);
                } else {
                    $account->refresh();
                    $newExpiresAt = $account->token_expires_at?->format('Y-m-d H:i:s') ?? 'N/A';
                    $this->info("    ✓ Refreshed successfully (new expiry: {$newExpiresAt})");
                    $refreshed++;

                    Log::info('Scheduled token refresh successful', [
                        'account_id' => $account->id,
                        'location_id' => $account->location_id,
                        'new_expires_at' => $account->token_expires_at,
                    ]);

                    // Also refresh location token if needed
                    if (empty($account->location_access_token) && $account->location_id) {
                        $this->line("    → Also refreshing location token...");
                        $this->highLevelService->exchangeCompanyTokenForLocation($account, $account->location_id);
                        $this->info("    ✓ Location token refreshed");
                    }
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("  Exception refreshing account {$account->id}: {$e->getMessage()}");
                $failed++;

                Log::error('Scheduled token refresh exception', [
                    'account_id' => $account->id,
                    'location_id' => $account->location_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        $this->newLine(2);
        $this->info("Token Refresh Summary:");
        $this->table(
            ['Status', 'Count'],
            [
                ['✓ Refreshed', $refreshed],
                ['✗ Failed', $failed],
                ['⊘ Skipped', $skipped],
                ['Total', $expiringAccounts->count()],
            ]
        );

        if ($failed > 0) {
            $this->warn("Some tokens failed to refresh. Check logs for details.");
            return Command::FAILURE;
        }

        $this->info("All tokens refreshed successfully!");
        return Command::SUCCESS;
    }
}
