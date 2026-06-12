<?php

use Illuminate\Support\Facades\Route;

/**
 * Imports Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - products.import → InventoryManager, Manager, BusinessAdmin (bulk product import)
 *
 * FIX APPLIED: 'role_or_permission:BusinessAdmin|InventoryManager' removed.
 *   Now uses 'permission:products.import' — the specific action this endpoint performs.
 */
Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
    Route::middleware('permission:products.import')->group(function () {
        Route::post('/data-migration/import/products', [\App\Http\Controllers\Api\V1\DataMigration\ImportController::class, 'importProducts']);
        Route::get('/data-migration/status/{id}', [\App\Http\Controllers\Api\V1\DataMigration\ImportController::class, 'getStatus']);
    });
});
