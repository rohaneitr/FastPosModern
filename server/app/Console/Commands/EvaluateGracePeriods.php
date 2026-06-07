<?php

namespace App\Console\Commands;

use App\Domain\Tenant\Models\DeviceActivation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * EvaluateGracePeriods  (Phase 3 – Offline Grace Period Enforcement)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Scans all active device activations and suspends those that have not sent a
 * heartbeat within their allowed offline grace period.
 *
 * Grace period rules (stored per-device in grace_period_days):
 *   hybrid → up to 30 days offline
 *   mobile → up to  7 days offline
 *   web    →  0 days (web clients must be online; no offline grace)
 *
 * Schedule (add to routes/console.php):
 *   Schedule::command('devices:evaluate-grace-periods')->hourly();
 *
 * Manual run:
 *   php artisan devices:evaluate-grace-periods
 *   php artisan devices:evaluate-grace-periods --dry-run   # preview only
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EvaluateGracePeriods extends Command
{
    protected $signature   = 'devices:evaluate-grace-periods {--dry-run : Preview without making changes}';
    protected $description = 'Suspend device activations that have exceeded their offline grace period';

    public function handle(): int
    {
        $isDryRun  = $this->option('dry-run');
        $now       = now();
        $suspended = 0;
        $warnings  = 0;

        $this->info('🔍 Scanning active device activations…');

        // Only evaluate 'active' rows — 'revoked' stays revoked, already-suspended
        // rows don't need re-processing.
        DeviceActivation::where('status', DeviceActivation::STATUS_ACTIVE)
            ->chunkById(200, function ($activations) use ($now, $isDryRun, &$suspended, &$warnings) {
                foreach ($activations as $activation) {
                    // ── Devices with grace_period_days = 0 (web plan) ────────
                    if ($activation->grace_period_days <= 0) {
                        // Web plan devices must always be online; skip — they
                        // don't participate in the heartbeat system.
                        continue;
                    }

                    // ── No heartbeat ever recorded ────────────────────────────
                    if (!$activation->last_synced_at) {
                        $this->warnRow($activation, 'no heartbeat ever recorded');
                        if (!$isDryRun) {
                            $activation->update(['status' => DeviceActivation::STATUS_SUSPENDED]);
                            $suspended++;
                        }
                        $warnings++;
                        continue;
                    }

                    $graceExpiresAt = $activation->gracePeriodExpiresAt();

                    // ── Within grace period ───────────────────────────────────
                    if ($graceExpiresAt && $graceExpiresAt->isFuture()) {
                        $daysLeft = round($now->floatDiffInDays($graceExpiresAt, false), 1);

                        // Warn when ≤ 3 days remain
                        if ($daysLeft <= 3) {
                            $this->line(sprintf(
                                "  ⚠️  <comment>ID:%d</comment> biz:%d — %s days remaining (expires %s)",
                                $activation->id,
                                $activation->business_id,
                                $daysLeft,
                                $graceExpiresAt->toDateTimeString()
                            ));
                            $warnings++;
                        }
                        continue;
                    }

                    // ── Grace period exceeded ─────────────────────────────────
                    $this->warnRow($activation, sprintf(
                        'last heartbeat %s (grace expired %s)',
                        $activation->last_synced_at->diffForHumans(),
                        $graceExpiresAt?->toDateTimeString() ?? 'N/A'
                    ));

                    if (!$isDryRun) {
                        $activation->update(['status' => DeviceActivation::STATUS_SUSPENDED]);
                    }

                    $suspended++;
                }
            });

        $this->newLine();

        if ($isDryRun) {
            $this->warn("DRY RUN — no changes written.");
            $this->info("Would suspend: {$suspended} device(s)  |  Warnings: {$warnings}");
        } else {
            $this->info("✅ Done — Suspended: {$suspended} device(s)  |  Warnings: {$warnings}");
        }

        return self::SUCCESS;
    }

    private function warnRow(DeviceActivation $activation, string $reason): void
    {
        $this->line(sprintf(
            "  🔴 <error>SUSPEND</error> ID:%d  biz:%d  fingerprint:%s — %s",
            $activation->id,
            $activation->business_id,
            substr($activation->device_fingerprint ?? 'none', 0, 12),
            $reason
        ));
    }
}
