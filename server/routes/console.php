<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
use Illuminate\Support\Facades\Schedule;

Schedule::command('saas:check-subscriptions')->daily();

// ── Phase 3: Evaluate offline grace periods every hour ────────────────────────
// Suspends DeviceActivation rows where last_synced_at exceeds grace_period_days.
Schedule::command('devices:evaluate-grace-periods')->hourly()->withoutOverlapping();

// ── Phase 36: Enterprise Automated Backups ────────────────────────────────────
Schedule::command('backup:run')->dailyAt('00:00')->withoutOverlapping();

// ── Automated Data Archiving Cleanup ──────────────────────────────────────────
Schedule::command('fpm:cleanup-archived-data')->weekly()->withoutOverlapping();

// ── Automated Trial Period Check ──────────────────────────────────────────────
Schedule::command('fpm:check-trial-status')->dailyAt('01:00')->withoutOverlapping();

Artisan::command('stress-test:superadmin', function () {
    $this->info("Running SuperAdmin API Stress Test...");
    
    // Auth bypass: Pick the first SuperAdmin user
    $user = \App\Modules\IAM\Models\User::whereHas('roles', function($q) { $q->where('name', 'SuperAdmin'); })->first();
    if (!$user) {
        $user = \App\Modules\IAM\Models\User::first();
        if (!$user) { $this->error("No user found."); return; }
    }

    $token = $user->createToken('stress-test')->plainTextToken;

    $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutesByMethod()['GET'];
    
    $tested = 0;
    $errors = 0;
    $results = [];

    foreach ($routes as $route) {
        if (str_starts_with($route->uri(), 'api/v1/superadmin')) {
            // Replace parameters with dummies if any (e.g. {id} -> 1)
            $uri = preg_replace('/\{.*?\}/', '1', $route->uri());
            
            try {
                $request = \Illuminate\Http\Request::create($uri, 'GET');
                $request->headers->set('Accept', 'application/json');
                $request->headers->set('Authorization', 'Bearer ' . $token);
                
                $response = app()->handle($request);
                $status = $response->getStatusCode();
                
                $color = $status >= 500 ? 'error' : ($status >= 400 ? 'warn' : 'info');
                if ($status >= 400) $errors++;
                $tested++;
                
                $this->$color("[$status] GET /$uri");
                $results[] = "[$status] GET /$uri";
                
                if ($status >= 500) {
                    $this->error("   Response: " . substr($response->getContent(), 0, 200));
                }
            } catch (\Throwable $e) {
                $this->error("[EXCEPTION] GET /$uri");
                $this->error("   " . $e->getMessage());
                $errors++;
                $tested++;
            }
        }
    }
    
    $this->info("Test Complete. Tested: $tested. Errors/Warnings: $errors.");
})->purpose('Stress tests all superadmin GET endpoints');
