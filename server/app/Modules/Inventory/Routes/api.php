<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module.access'])->group(function () {
    // Catalog Domain (Read/Print Access)
    Route::middleware('role_or_permission:BusinessAdmin|InventoryManager|Cashier')->group(function () {
        Route::post('products/print-labels', [\App\Modules\Inventory\Controllers\ProductController::class, 'printLabels']);
        Route::get('products/{product}/serials', [\App\Modules\Inventory\Controllers\ProductController::class, 'serials']);
        Route::apiResource('products', \App\Modules\Inventory\Controllers\ProductController::class)->only(['index', 'show']);
        Route::apiResource('categories', \App\Modules\Inventory\Controllers\CategoryController::class)->only(['index', 'show']);
        Route::apiResource('brands', \App\Modules\Inventory\Controllers\BrandController::class)->only(['index', 'show']);
    });

    // Catalog Domain (Write Access)
    Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
        Route::apiResource('products', \App\Modules\Inventory\Controllers\ProductController::class)->only(['store', 'update']);
        Route::apiResource('categories', \App\Modules\Inventory\Controllers\CategoryController::class)->only(['store', 'update']);
        Route::apiResource('brands', \App\Modules\Inventory\Controllers\BrandController::class)->only(['store', 'update']);
        
        // Strict Spatie Guardrails for Destructive Actions
        Route::delete('products/{product}', [\App\Modules\Inventory\Controllers\ProductController::class, 'destroy'])->middleware('permission:products.manage');
        Route::delete('categories/{category}', [\App\Modules\Inventory\Controllers\CategoryController::class, 'destroy'])->middleware('permission:products.manage');
        Route::delete('brands/{brand}', [\App\Modules\Inventory\Controllers\BrandController::class, 'destroy'])->middleware('permission:products.manage');
    });

    // Core Inventory Domain
    Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
        Route::get('/inventory/stock', [\App\Modules\Inventory\Controllers\InventoryController::class, 'stock']);
        Route::get('/inventory/layers', [\App\Modules\Inventory\Controllers\InventoryController::class, 'layers']);
        Route::post('/inventory/adjust', [\App\Modules\Inventory\Controllers\InventoryController::class, 'adjustStock']);
        Route::post('/inventory/transfer', [\App\Modules\Inventory\Controllers\InventoryController::class, 'transferStock']);
        Route::get('/inventory/low-stock', [\App\Modules\Inventory\Controllers\InventoryController::class, 'lowStock']);
        Route::get('/inventory/history', [\App\Modules\Inventory\Controllers\InventoryController::class, 'history']);
        Route::get('/inventory/pending-sourcing', [\App\Modules\Inventory\Controllers\InventoryController::class, 'pendingSourcing']);
    });
});
