<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_procurement'])->group(function () {
    Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
        Route::apiResource('suppliers', \App\Modules\Procurement\Controllers\SupplierController::class)->except(['destroy']);
        Route::apiResource('purchases', \App\Modules\Procurement\Controllers\PurchaseController::class)->except(['destroy']);
        
        // Strict Spatie Guardrails for Destructive Actions
        Route::delete('suppliers/{supplier}', [\App\Modules\Procurement\Controllers\SupplierController::class, 'destroy'])->middleware('permission:supplier.delete');
        Route::delete('purchases/{purchase}', [\App\Modules\Procurement\Controllers\PurchaseController::class, 'destroy'])->middleware('permission:purchase.delete');
    });
});
