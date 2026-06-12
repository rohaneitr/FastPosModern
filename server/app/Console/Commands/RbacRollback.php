<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * RbacRollback — Single-Command Emergency Rollback
 *
 * Execution: php artisan rbac:rollback
 *
 * This command restores the RBAC route layer to the last known-good
 * forensic audit checkpoint (git commit: 7f6ed77) and flushes all
 * Spatie permission caches. It is safe to run in production.
 *
 * Rollback targets:
 *   - server/routes/api.php                         (route middleware)
 *   - server/bootstrap/app.php                      (middleware aliases)
 *   - server/app/Modules/Catalog/.../InventoryController.php  (Gate injection)
 *   - client/src/components/CommandPalette.tsx      (frontend strings)
 *   - client/src/components/layout/sidebar/sidebar-config.ts
 *
 * SAFE TO RUN: No database mutations. Only file + cache changes.
 */
class RbacRollback extends Command
{
    protected $signature = 'rbac:rollback
        {--dry-run : Show what would be reverted without making any changes}
        {--commit= : Target git commit to restore to (default: 7f6ed77)}';

    protected $description = 'Emergency rollback: restore RBAC routes to last known-good checkpoint';

    // Commit hash of the forensic audit checkpoint (pre-patch state)
    private const CHECKPOINT_COMMIT = '7f6ed77';

    // Files touched by the RBAC patch — only these are reverted
    private const PATCHED_FILES = [
        'server/routes/api.php',
        'server/bootstrap/app.php',
        'server/app/Modules/Catalog/Controllers/InventoryController.php',
        'client/src/components/CommandPalette.tsx',
        'client/src/components/layout/sidebar/sidebar-config.ts',
    ];

    public function handle(): int
    {
        $targetCommit = $this->option('commit') ?? self::CHECKPOINT_COMMIT;
        $isDryRun = $this->option('dry-run');

        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║         RBAC EMERGENCY ROLLBACK SYSTEM                  ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('[DRY RUN] No changes will be made.');
        }

        // Step 1: Verify git is available and the target commit exists
        $this->info('Step 1: Verifying rollback target...');
        exec("git cat-file -t {$targetCommit} 2>&1", $output, $code);

        if ($code !== 0 || trim(implode('', $output)) !== 'commit') {
            $this->error("Rollback ABORTED: Commit {$targetCommit} not found in repository.");
            $this->error('Run `git log --oneline` to find a valid checkpoint commit hash.');
            return self::FAILURE;
        }
        $this->line("  ✓ Checkpoint commit {$targetCommit} verified.");

        // Step 2: Show what will be reverted
        $this->newLine();
        $this->info('Step 2: Files to be restored:');
        foreach (self::PATCHED_FILES as $file) {
            $this->line("  → {$file}");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->warn('[DRY RUN] Stopping before applying changes.');
            return self::SUCCESS;
        }

        // Step 3: Confirm
        if (!$this->confirm("\nRestore these files to commit {$targetCommit}? This CANNOT be undone without git.")) {
            $this->warn('Rollback cancelled by user.');
            return self::SUCCESS;
        }

        // Step 4: Restore files from checkpoint commit
        $this->newLine();
        $this->info('Step 3: Restoring files...');
        $failed = [];

        foreach (self::PATCHED_FILES as $file) {
            exec("git checkout {$targetCommit} -- {$file} 2>&1", $out, $exitCode);

            if ($exitCode === 0) {
                $this->line("  ✓ Restored: {$file}");
            } else {
                $this->error("  ✗ FAILED:   {$file} — " . implode(' ', $out));
                $failed[] = $file;
            }
        }

        // Step 5: Flush Spatie permission cache
        $this->newLine();
        $this->info('Step 4: Flushing Spatie permission cache...');
        try {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->line('  ✓ Permission cache cleared.');
        } catch (\Throwable $e) {
            $this->warn('  ⚠ Could not flush permission cache: ' . $e->getMessage());
        }

        // Step 6: Summary
        $this->newLine();
        if (empty($failed)) {
            $this->line('╔══════════════════════════════════════════════════════════╗');
            $this->line('║  ROLLBACK COMPLETE — System restored to checkpoint.     ║');
            $this->line('╚══════════════════════════════════════════════════════════╝');
            $this->newLine();
            $this->info("To re-apply the patch, run: git cherry-pick 761c32f");
            $this->info("To verify RBAC state, run:  php artisan rbac:audit");
            return self::SUCCESS;
        }

        $this->error('ROLLBACK PARTIAL — ' . count($failed) . ' file(s) could not be restored.');
        $this->error('Manually restore via: git checkout ' . $targetCommit . ' -- <file>');
        return self::FAILURE;
    }
}
