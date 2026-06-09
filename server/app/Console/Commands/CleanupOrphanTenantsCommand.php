<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Services\TenantDeletionService;

/**
 * php artisan tenant:cleanup-orphans
 *
 * Finds every soft-deleted (deleted_at IS NOT NULL) business record and passes
 * its ID through TenantDeletionService::wipeTenant() to permanently erase all
 * child records and the business row itself.
 *
 * Also catches any businesses whose owner_id references a non-existent user
 * (structural orphans from failed registrations).
 *
 * SAFE TO RE-RUN: each wipe is wrapped in its own transaction; a single
 * failure is logged and skipped so the command keeps processing the rest.
 */
class CleanupOrphanTenantsCommand extends Command
{
    protected $signature   = 'tenant:cleanup-orphans
                              {--dry-run : List orphans without deleting them}
                              {--id=    : Target a specific business ID instead of scanning}';

    protected $description = 'Hard-delete all soft-deleted (orphaned) tenants and their associated data.';

    public function handle(TenantDeletionService $deletionService): int
    {
        $dryRun   = $this->option('dry-run');
        $targetId = $this->option('id');

        $this->info('═══════════════════════════════════════════════════');
        $this->info('  FastPOS – Orphan Tenant Cleanup');
        $this->info('  Mode: ' . ($dryRun ? '🔍 Dry Run (no changes)' : '🗑  Live Delete'));
        $this->info('═══════════════════════════════════════════════════');

        // Build candidate list
        $query = DB::table('businesses');

        if ($targetId) {
            $query->where('id', (int) $targetId);
        } else {
            $query->where(function ($q) {
                // Soft-deleted rows
                $q->whereNotNull('deleted_at')
                  // OR businesses whose owner user is gone
                  ->orWhereNotExists(function ($sub) {
                      $sub->select(DB::raw(1))
                          ->from('users')
                          ->whereColumn('users.id', 'businesses.owner_id');
                  });
            });
        }

        $candidates = $query->get(['id', 'name', 'deleted_at']);

        if ($candidates->isEmpty()) {
            $this->info('✅  No orphaned tenants found. Database is clean.');
            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Deleted At'],
            $candidates->map(fn ($b) => [
                $b->id,
                $b->name ?? '(null)',
                $b->deleted_at ?? '—',
            ])->all()
        );

        if ($dryRun) {
            $this->warn('Dry-run mode: no data was modified.');
            return Command::SUCCESS;
        }

        if (!$this->confirm("Permanently delete {$candidates->count()} tenant(s) and ALL their data? This cannot be undone.", false)) {
            $this->line('Aborted.');
            return Command::SUCCESS;
        }

        $success = 0;
        $failed  = 0;

        foreach ($candidates as $business) {
            $this->line("  ⏳ Wiping tenant #{$business->id} ({$business->name}) …");

            try {
                $result = $deletionService->wipeTenant((int) $business->id);

                $this->line("  <fg=green>✓</> Tenant #{$business->id} wiped. Summary:");
                foreach ($result['summary'] as $table => $rows) {
                    if ($rows > 0) {
                        $this->line("      {$table}: {$rows} rows deleted");
                    }
                }
                $success++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed to wipe tenant #{$business->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("═══════════════════════════════════════════════════");
        $this->info("  Done – {$success} wiped, {$failed} failed.");
        $this->info("═══════════════════════════════════════════════════");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
