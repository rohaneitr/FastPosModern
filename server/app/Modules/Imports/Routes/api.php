<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
    Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
        // Bulk Data Migration & Imports
        Route::post('/data-migration/import/products', [\App\Http\Controllers\Api\V1\DataMigration\ImportController::class, 'importProducts']);
        Route::get('/data-migration/status/{id}', [\App\Http\Controllers\Api\V1\DataMigration\ImportController::class, 'getStatus']);
    });
});
