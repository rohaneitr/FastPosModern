<?php

use Illuminate\Support\Facades\Route;

/**
 * Reporting Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - reports.view   → Accountant, Manager, BusinessAdmin (KPIs, dashboard, sales report)
 *  - reports.export → Accountant, BusinessAdmin (PDF export — slightly more restrictive)
 *  - accounting.view→ Accountant, BusinessAdmin (GL-level overview)
 *  - sales.view     → Cashier and above (consolidated overview uses sales data)
 *
 * NOTE: Dashboard /stats endpoint is intentionally open to ALL authenticated+subscribed users
 *   (Cashiers need their session's register summary). This is a design decision.
 */
Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {

    // Dashboard stats: available to all authenticated subscribed users
    // (Cashiers see their own register summary; Admins see full business summary)
    Route::get('/dashboard/stats', [\App\Modules\Reporting\Controllers\TenantDashboardController::class, 'stats']);

    // Consolidated analytics overview: requires sales data visibility
    Route::middleware('permission:sales.view')->group(function () {
        Route::prefix('analytics')->group(function () {
            Route::get('/consolidated-overview', [\App\Modules\Reporting\Controllers\UnifiedAnalyticsController::class, 'getConsolidatedOverview']);
        });
    });

    // Accounting-level analytics: requires accounting.view
    Route::middleware('permission:accounting.view')->group(function () {
        Route::get('/analytics/overview', [\App\Http\Controllers\Api\V1\Analytics\AnalyticsController::class, 'overview']);
    });

    // Financial Reports: requires reports.view (Accountant + BusinessAdmin)
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/dashboard', [\App\Modules\Reporting\Controllers\ReportController::class, 'dashboardKPIs']);
        Route::get('/reports/profit-loss', [\App\Modules\Reporting\Controllers\ReportController::class, 'profitLoss']);
        Route::get('/reports/sales', [\App\Modules\Reporting\Controllers\ReportController::class, 'salesReport']);
        Route::get('/reports/inventory-valuation', [\App\Modules\Reporting\Controllers\ReportController::class, 'inventoryValuation']);
        Route::get('/invoices/{id}', [\App\Modules\Reporting\Controllers\InvoiceController::class, 'show']);
        Route::get('/invoices/{id}/print', [\App\Modules\Reporting\Controllers\InvoiceController::class, 'printView']);
    });

    // PDF Export: requires reports.export (slightly more restrictive than view)
    Route::middleware('permission:reports.export')->group(function () {
        Route::get('/reports/sales/export-pdf', [\App\Modules\Reporting\Controllers\ReportController::class, 'exportPdf']);
    });
});
