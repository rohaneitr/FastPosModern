<?php

use Illuminate\Support\Facades\Route;

/**
 * Inventory Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - All gates use permission:X (never role:X — roles are assigned permissions, not hardcoded here).
 *  - 'products.view' → Cashier, InventoryManager, Manager, BusinessAdmin
 *  - 'products.edit' → InventoryManager, Manager, BusinessAdmin
 *  - 'products.delete' → BusinessAdmin only (most destructive)
 *  - 'inventory.view' → Cashier, InventoryManager, Manager, BusinessAdmin
 *  - 'inventory.adjust' → InventoryManager, Manager, BusinessAdmin
 */
Route::middleware(['auth:sanctum', 'module.access'])->group(function () {

    // ── READ ACCESS: Products, Categories, Brands ─────────────────────────────
    // Cashiers need to view products to operate POS. InventoryManager needs full catalog access.
    Route::middleware('permission:products.view')->group(function () {
        Route::post('products/print-labels', [\App\Modules\Inventory\Controllers\ProductController::class, 'printLabels'])
            ->middleware('permission:inventory.labels');
        Route::get('products/{product}/serials', [\App\Modules\Inventory\Controllers\ProductController::class, 'serials']);
        Route::apiResource('products', \App\Modules\Inventory\Controllers\ProductController::class)->only(['index', 'show']);
        Route::apiResource('categories', \App\Modules\Inventory\Controllers\CategoryController::class)->only(['index', 'show']);
        Route::apiResource('brands', \App\Modules\Inventory\Controllers\BrandController::class)->only(['index', 'show']);
    });

    // ── WRITE ACCESS: Products ────────────────────────────────────────────────
    Route::middleware('permission:products.create')->group(function () {
        Route::apiResource('products', \App\Modules\Inventory\Controllers\ProductController::class)->only(['store']);
    });

    Route::middleware('permission:products.edit')->group(function () {
        Route::apiResource('products', \App\Modules\Inventory\Controllers\ProductController::class)->only(['update']);
    });

    // ── DELETE ACCESS: Products (most destructive — highest permission required) ─
    Route::delete('products/{product}', [\App\Modules\Inventory\Controllers\ProductController::class, 'destroy'])
        ->middleware('permission:products.delete');

    // ── WRITE ACCESS: Categories, Brands ─────────────────────────────────────
    Route::middleware('permission:categories.manage')->group(function () {
        Route::apiResource('categories', \App\Modules\Inventory\Controllers\CategoryController::class)->only(['store', 'update']);
        Route::delete('categories/{category}', [\App\Modules\Inventory\Controllers\CategoryController::class, 'destroy']);
    });

    Route::middleware('permission:brands.manage')->group(function () {
        Route::apiResource('brands', \App\Modules\Inventory\Controllers\BrandController::class)->only(['store', 'update']);
        Route::delete('brands/{brand}', [\App\Modules\Inventory\Controllers\BrandController::class, 'destroy']);
    });

    // ── INVENTORY OPERATIONS ──────────────────────────────────────────────────
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/inventory/stock', [\App\Modules\Inventory\Controllers\InventoryController::class, 'stock']);
        Route::get('/inventory/layers', [\App\Modules\Inventory\Controllers\InventoryController::class, 'layers']);
        Route::get('/inventory/low-stock', [\App\Modules\Inventory\Controllers\InventoryController::class, 'lowStock']);
        Route::get('/inventory/history', [\App\Modules\Inventory\Controllers\InventoryController::class, 'history']);
        Route::get('/inventory/pending-sourcing', [\App\Modules\Inventory\Controllers\InventoryController::class, 'pendingSourcing']);
    });

    Route::middleware('permission:inventory.adjust')->group(function () {
        Route::post('/inventory/adjust', [\App\Modules\Inventory\Controllers\InventoryController::class, 'adjustStock']);
    });

    Route::middleware('permission:inventory.transfer')->group(function () {
        Route::post('/inventory/transfer', [\App\Modules\Inventory\Controllers\InventoryController::class, 'transferStock']);
    });
});
