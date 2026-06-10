<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_crm'])->group(function () {
    Route::middleware('role_or_permission:BusinessAdmin|Cashier')->group(function () {
        Route::apiResource('contacts', \App\Modules\CRM\Controllers\ContactController::class)->except(['destroy']);
        Route::delete('contacts/{contact}', [\App\Modules\CRM\Controllers\ContactController::class, 'destroy'])->middleware('permission:tenant.manage');
        Route::post('/messages/bulk', [\App\Modules\CRM\Controllers\MessageController::class, 'sendBulk']);
    });
});

Route::middleware(['auth:sanctum', 'abilities:customer:read-own-data', 'module.access:core_crm'])->group(function () {
    Route::get('/customer/dashboard-metrics', [\App\Modules\CRM\Controllers\CustomerPortalController::class, 'dashboardMetrics']);
});
