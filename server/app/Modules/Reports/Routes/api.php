<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed', 'role_or_permission:BusinessAdmin'])->group(function () {
    Route::get('/reports/pnl', [\App\Modules\Reports\Controllers\FinancialReportController::class, 'profitAndLoss']);
});
