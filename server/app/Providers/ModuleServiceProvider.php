<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * ModuleServiceProvider
 *
 * Centralized bootstrapper for all feature modules under app/Modules/.
 * Automatically discovers and registers:
 *
 *   1. Migrations      — module/Database/Migrations/
 *   2. API Routes      — module/Routes/api.php  (prefixed: api/v1, middleware: api)
 *   3. Web Routes      — module/Routes/web.php  (middleware: web)
 *   4. Console Commands — module/Console/**Command.php (auto-discovered, zero hardcoding)
 *
 * ZERO TRUST DESIGN:
 *   - No module name is ever hardcoded here.
 *   - Commands are discovered by Finder scanning for classes ending in 'Command.php'.
 *   - Class FQCNs are derived from the module namespace convention:
 *     App\Modules\{Module}\Console\{ClassName}
 *   - If a class does not exist (e.g. abstract or non-command), it is silently skipped.
 *
 * HOW TO ADD A NEW MODULE:
 *   - Create the directory: app/Modules/{ModuleName}/
 *   - Drop routes in  app/Modules/{ModuleName}/Routes/api.php
 *   - Drop commands in app/Modules/{ModuleName}/Console/
 *   - Zero changes needed here — auto-detected.
 *
 * @version Phase 5 — Task 5.4 (2026-06-12)
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $modulesPath = app_path('Modules');

        if (! File::isDirectory($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $modulePath) {
            $this->loadModuleMigrations($modulePath);
            $this->loadModuleRoutes($modulePath);
            $this->loadModuleCommands($modulePath);
        }
    }

    // ── Migrations ────────────────────────────────────────────────────────

    private function loadModuleMigrations(string $modulePath): void
    {
        $path = $modulePath . '/Database/Migrations';
        if (File::isDirectory($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    // ── Routes ────────────────────────────────────────────────────────────

    private function loadModuleRoutes(string $modulePath): void
    {
        $apiRoute = $modulePath . '/Routes/api.php';
        if (File::exists($apiRoute)) {
            Route::prefix('api/v1')
                ->middleware(['api'])
                ->group($apiRoute);
        }

        $webRoute = $modulePath . '/Routes/web.php';
        if (File::exists($webRoute)) {
            Route::middleware('web')->group($webRoute);
        }
    }

    // ── Console Commands — Zero-hardcoding Auto-Discovery ─────────────────

    private function loadModuleCommands(string $modulePath): void
    {
        $consolePath = $modulePath . '/Console';
        if (! File::isDirectory($consolePath)) {
            return;
        }

        $commands = [];

        // Derive module name from directory: app/Modules/{ModuleName}
        $moduleName = basename($modulePath);

        $finder = Finder::create()
            ->files()
            ->name('*Command.php')
            ->in($consolePath);

        foreach ($finder as $file) {
            // Build FQCN from convention: App\Modules\{Module}\Console\{ClassName}
            // Handles nested subdirectories: Console/Foo/BarCommand.php →
            // App\Modules\{Module}\Console\Foo\BarCommand
            $relativePath = $file->getRelativePathname(); // e.g. BackfillLedgerCommand.php
            $className    = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $fqcn         = "App\\Modules\\{$moduleName}\\Console\\{$className}";

            if (class_exists($fqcn)) {
                $commands[] = $fqcn;
            }
        }

        if (! empty($commands)) {
            $this->commands($commands);
        }
    }
}
