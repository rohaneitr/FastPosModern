<?php

namespace App\Console\Commands;

use App\Modules\Tenant\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SuspendExpiredTrials — Phase 7: Trial Lifecycle Auto-Suspension
 *
 * Suspends businesses whose free trial has expired and who have not
 * converted to a paid subscription.
 *
 * ── QUERY LOGIC ────────────────────────────────────────────────────────────
 * Target businesses match ALL three conditions:
 *   1. trial_ends_at IS NOT NULL AND trial_ends_at < NOW()
 *      → The trial period has ended (not ongoing, not null = no trial)
 *   2. subscription_status != 'active'
 *      → They have NOT converted to a paying subscription
 *      → Explicitly excludes businesses Stripe already activated via webhook
 *   3. is_active = true
 *      → They are still active (not already suspended by a prior run or webhook)
 *      → Prevents re-updating already-suspended rows (idempotent)
 *
 * ── SAFETY CONTRACT ────────────────────────────────────────────────────────
 * - Data is NEVER deleted. is_active = false is a soft suspension gate.
 * - The middleware `subscribed` checks is_active — suspended businesses
 *   get a 402 response on all tenant API calls without touching their data.
 * - The command is idempotent: running it twice on the same day produces
 *   the same result (condition 3 ensures already-suspended businesses are skipped).
 *
 * ── CHUNK PROCESSING ───────────────────────────────────────────────────────
 * Uses chunkById() instead of get() to prevent loading thousands of Business
 * models into memory at once. Each chunk is processed inside a DB::transaction
 * so a mid-chunk failure doesn't leave a partial batch suspended.
 *
 * ── SCHEDULING ─────────────────────────────────────────────────────────────
 * Registered in routes/console.php to run daily at 02:00 UTC:
 *   Schedule::command('fpm:suspend-expired-trials')->dailyAt('02:00')->withoutOverlapping();
 *
 * @version Phase 7 — Trial Lifecycle Engine
 */
class SuspendExpiredTrials extends Command
{
    protected $signature = 'fpm:suspend-expired-trials
                            {--dry-run : List candidates without suspending them}';

    protected $description = 'Suspend businesses whose free trial has expired without converting to a paid subscription.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $now      = Carbon::now();

        $this->info("[{$now->toDateTimeString()}] Running: fpm:suspend-expired-trials" . ($isDryRun ? ' (DRY RUN)' : ''));

        // ── Build the candidate query ──────────────────────────────────────────
        // Explicitly disable the BelongsToBusiness global scope — this command
        // runs as a CLI process with no authenticated user. The global scope's
        // FAIL CLOSED guard (whereRaw('1 = 0')) would silently return no results.
        $query = Business::withoutGlobalScopes()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->where('subscription_status', '!=', 'active')
            ->where('is_active', true);

        $totalCandidates = $query->count();

        if ($totalCandidates === 0) {
            $this->info('No expired trials found. Nothing to suspend.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalCandidates} expired trial(s) to suspend.");

        if ($isDryRun) {
            $this->table(
                ['ID', 'Name', 'Trial Ends At', 'Subscription Status'],
                $query->get(['id', 'name', 'trial_ends_at', 'subscription_status'])->map(fn($b) => [
                    $b->id,
                    $b->name,
                    $b->trial_ends_at?->toDateTimeString(),
                    $b->subscription_status,
                ])
            );
            $this->warn('DRY RUN — no changes were made.');
            return Command::SUCCESS;
        }

        // ── Process in chunks to avoid memory exhaustion ───────────────────────
        $suspendedCount = 0;
        $failedIds      = [];

        $query->chunkById(100, function ($businesses) use (&$suspendedCount, &$failedIds) {
            try {
                DB::transaction(function () use ($businesses, &$suspendedCount) {
                    foreach ($businesses as $business) {
                        $business->is_active           = false;
                        $business->subscription_status = 'expired';
                        $business->save();

                        Log::channel('single')->info('SuspendExpiredTrials: Suspended business.', [
                            'business_id'         => $business->id,
                            'name'                => $business->name,
                            'trial_ends_at'       => $business->trial_ends_at?->toDateTimeString(),
                            'subscription_status' => $business->subscription_status,
                        ]);

                        $suspendedCount++;
                    }
                });
            } catch (\Exception $e) {
                $ids = $businesses->pluck('id')->toArray();
                $failedIds = array_merge($failedIds, $ids);

                Log::error('SuspendExpiredTrials: Chunk transaction failed.', [
                    'business_ids' => $ids,
                    'error'        => $e->getMessage(),
                ]);
            }
        });

        // ── Output summary ─────────────────────────────────────────────────────
        $this->info("Suspended: {$suspendedCount} business(es).");

        if (! empty($failedIds)) {
            $this->error('Failed to suspend: ' . implode(', ', $failedIds));
            Log::error('SuspendExpiredTrials: Some businesses failed to suspend.', [
                'failed_ids' => $failedIds,
            ]);
            return Command::FAILURE;
        }

        Log::info('SuspendExpiredTrials: Completed.', [
            'suspended_count' => $suspendedCount,
            'run_at'          => Carbon::now()->toIso8601String(),
        ]);

        return Command::SUCCESS;
    }
}
