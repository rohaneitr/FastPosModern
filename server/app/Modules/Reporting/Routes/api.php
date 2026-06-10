<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
    
    // Analytics Domain
    Route::prefix('analytics')->group(function () {
        Route::get('/consolidated-overview', [\App\Modules\Reporting\Controllers\UnifiedAnalyticsController::class, 'getConsolidatedOverview']);
    });

    // Accounting & Reporting
    Route::middleware('role_or_permission:BusinessAdmin')->group(function () {
        Route::get('/analytics/overview', [\App\Http\Controllers\Api\V1\Analytics\AnalyticsController::class, 'overview']);
    });

    Route::middleware('role_or_permission:BusinessAdmin|Accountant')->group(function () {
        Route::get('/reports/dashboard', [\App\Modules\Reporting\Controllers\ReportController::class, 'dashboardKPIs']);
        Route::get('/reports/profit-loss', [\App\Modules\Reporting\Controllers\ReportController::class, 'profitLoss']);
        Route::get('/reports/sales', [\App\Modules\Reporting\Controllers\ReportController::class, 'salesReport']);
        Route::get('/reports/inventory-valuation', [\App\Modules\Reporting\Controllers\ReportController::class, 'inventoryValuation']);
        Route::get('/reports/sales/export-pdf', [\App\Modules\Reporting\Controllers\ReportController::class, 'exportPdf']);
        Route::get('/invoices/{id}', [\App\Modules\Reporting\Controllers\InvoiceController::class, 'show']);
        Route::get('/invoices/{id}/print', [\App\Modules\Reporting\Controllers\InvoiceController::class, 'printView']);
    });

});
