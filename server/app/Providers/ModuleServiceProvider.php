<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $modulesPath = app_path('Modules');

        if (File::exists($modulesPath)) {
            $modules = File::directories($modulesPath);

            foreach ($modules as $module) {
                // Load Migrations
                $migrationPath = $module . '/Database/Migrations';
                if (File::exists($migrationPath)) {
                    $this->loadMigrationsFrom($migrationPath);
                }

                // Load API Routes
                $apiRoutePath = $module . '/Routes/api.php';
                if (File::exists($apiRoutePath)) {
                    Route::prefix('api/v1')
                        ->middleware(['api'])
                        ->group($apiRoutePath);
                }
                
                // Load Web Routes (if needed later)
                $webRoutePath = $module . '/Routes/web.php';
                if (File::exists($webRoutePath)) {
                    Route::middleware('web')->group($webRoutePath);
                }
                // Load Console Commands
                $consolePath = $module . '/Console';
                if (File::exists($consolePath)) {
                    $this->commands([
                        \App\Modules\Reports\Console\BackfillLedgerCommand::class,
                    ]);
                }
            }
        }
    }
}
