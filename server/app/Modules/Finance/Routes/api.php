<?php

use Illuminate\Support\Facades\Route;

/**
 * Finance Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - accounting.view → Accountant, BusinessAdmin (GL, P&L, Balance Sheet)
 *  - expenses.view   → Accountant, BusinessAdmin
 *  - expenses.create → Accountant, Manager, BusinessAdmin
 *  - expenses.edit   → Accountant, BusinessAdmin
 *  - expenses.delete → BusinessAdmin only (financial record destruction is irreversible)
 *
 * FIX APPLIED: DELETE /expenses was previously gated by 'permission:reports.manage' — WRONG.
 *   reports.manage is a view/read permission for reports.
 *   Deleting a financial expense record is destructive → now correctly gated by 'permission:expenses.delete'.
 */
Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_accounting'])->group(function () {

    // Accounting Reports (read-only financial statements)
    Route::middleware('permission:accounting.view')->group(function () {
        Route::get('/accounting/trial-balance', [\App\Modules\Finance\Controllers\FinancialReportingController::class, 'getTrialBalance']);
        Route::get('/accounting/profit-and-loss', [\App\Modules\Finance\Controllers\FinancialReportingController::class, 'getProfitAndLoss']);
        Route::get('/accounting/balance-sheet', [\App\Modules\Finance\Controllers\FinancialReportingController::class, 'getBalanceSheet']);
    });

    // Expenses — CRUD with granular permission gates
    Route::middleware('permission:expenses.view')->group(function () {
        Route::apiResource('expenses', \App\Modules\Finance\Controllers\ExpenseController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:expenses.create')->group(function () {
        Route::apiResource('expenses', \App\Modules\Finance\Controllers\ExpenseController::class)->only(['store']);
    });

    Route::middleware('permission:expenses.edit')->group(function () {
        Route::apiResource('expenses', \App\Modules\Finance\Controllers\ExpenseController::class)->only(['update']);
    });

    // DELETE: Requires specific expenses.delete permission (NOT reports.manage — fixed)
    Route::delete('expenses/{expense}', [\App\Modules\Finance\Controllers\ExpenseController::class, 'destroy'])
        ->middleware('permission:expenses.delete');
});
