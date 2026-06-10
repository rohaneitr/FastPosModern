<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_accounting'])->group(function () {
    Route::middleware('role_or_permission:BusinessAdmin|Accountant')->group(function () {
        Route::get('/accounting/trial-balance', [\App\Modules\Finance\Controllers\FinancialReportingController::class, 'getTrialBalance']);
        Route::get('/accounting/profit-and-loss', [\App\Modules\Finance\Controllers\FinancialReportingController::class, 'getProfitAndLoss']);
        Route::get('/accounting/balance-sheet', [\App\Modules\Finance\Controllers\FinancialReportingController::class, 'getBalanceSheet']);
        Route::apiResource('expenses', \App\Modules\Finance\Controllers\ExpenseController::class)->except(['destroy']);
        
        // Strict Spatie Guardrails for Destructive Actions
        Route::delete('expenses/{expense}', [\App\Modules\Finance\Controllers\ExpenseController::class, 'destroy'])->middleware('permission:expense.delete');
    });
});
