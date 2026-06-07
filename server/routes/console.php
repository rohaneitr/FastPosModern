<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('saas:check-subscriptions')->daily();

// ── Phase 3: Evaluate offline grace periods every hour ────────────────────────
// Suspends DeviceActivation rows where last_synced_at exceeds grace_period_days.
Schedule::command('devices:evaluate-grace-periods')->hourly()->withoutOverlapping();

// ── Phase 36: Enterprise Automated Backups ────────────────────────────────────
Schedule::command('backup:run')->dailyAt('00:00')->withoutOverlapping();
