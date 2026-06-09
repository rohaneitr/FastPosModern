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
