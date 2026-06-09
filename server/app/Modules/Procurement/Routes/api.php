<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_procurement'])->group(function () {
    Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
        Route::apiResource('suppliers', \App\Modules\Procurement\Controllers\SupplierController::class);
        Route::apiResource('purchases', \App\Modules\Procurement\Controllers\PurchaseController::class);
    });
});
