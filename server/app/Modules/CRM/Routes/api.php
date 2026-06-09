<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_crm'])->group(function () {
    Route::middleware('role_or_permission:BusinessAdmin|Cashier')->group(function () {
        Route::apiResource('contacts', \App\Modules\CRM\Controllers\ContactController::class);
    });
});

Route::middleware(['auth:sanctum', 'abilities:customer:read-own-data', 'module.access:core_crm'])->group(function () {
    Route::get('/customer/dashboard-metrics', [\App\Modules\CRM\Controllers\CustomerPortalController::class, 'dashboardMetrics']);
});
