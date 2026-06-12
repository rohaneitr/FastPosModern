<?php

use Illuminate\Support\Facades\Route;

/**
 * Reports Module Routes — FastPOS Modern
 *
 * NOTE: This module contains a legacy financial report endpoint.
 * It is scheduled to be merged into the Reporting module (Phase 5 roadmap).
 * Until then, it uses the correct granular permission gate.
 *
 * RBAC: reports.view (Accountant, Manager, BusinessAdmin)
 */
Route::middleware(['auth:sanctum', 'subscribed', 'permission:reports.view'])->group(function () {
    Route::get('/reports/pnl', [\App\Modules\Reports\Controllers\FinancialReportController::class, 'profitAndLoss']);
});
