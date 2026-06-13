<?php

/**
 * Inventory Routes
 *
 * Covers: products (CRUD), categories, brands, units,
 * inventory transfers, and procurement (purchases/suppliers).
 * All routes: auth:sanctum + subscribed.
 * Granular permissions enforced per operation group.
 *
 * Loaded by RouteServiceProvider under prefix: api/v1
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {

    // ── Products — products.view (Cashier read) / products.manage (Admin write) ──
    Route::middleware('permission:products.view')->group(function () {
        Route::get('/tenant/products',       [\App\Modules\Inventory\Controllers\ProductController::class, 'index']);
        Route::get('/tenant/products/{id}',  [\App\Modules\Inventory\Controllers\ProductController::class, 'show']);
    });

    Route::middleware('permission:products.manage')->group(function () {
        Route::post('/tenant/products',         [\App\Modules\Inventory\Controllers\ProductController::class, 'store']);
        Route::put('/tenant/products/{id}',     [\App\Modules\Inventory\Controllers\ProductController::class, 'update']);
        Route::delete('/tenant/products/{id}',  [\App\Modules\Inventory\Controllers\ProductController::class, 'destroy']);
        Route::post('/tenant/products/import',  [\App\Modules\Inventory\Controllers\ProductController::class, 'import']);
    });

    // ── Catalog Lookups (used by Cashier POS screen) ───────────────────────────
    Route::middleware('permission:products.view')->group(function () {
        Route::apiResource('tenant/categories', \App\Modules\Inventory\Controllers\CategoryController::class)
            ->only(['index', 'show']);
        Route::apiResource('tenant/brands',     \App\Modules\Inventory\Controllers\BrandController::class)
            ->only(['index', 'show']);
        Route::apiResource('tenant/units',      \App\Modules\Inventory\Controllers\UnitController::class)
            ->only(['index', 'show']);
    });

    // Catalog write operations (Admin/InventoryManager only)
    Route::middleware('permission:products.manage')->group(function () {
        Route::apiResource('tenant/categories', \App\Modules\Inventory\Controllers\CategoryController::class)
            ->except(['index', 'show']);
        Route::apiResource('tenant/brands',     \App\Modules\Inventory\Controllers\BrandController::class)
            ->except(['index', 'show']);
        Route::apiResource('tenant/units',      \App\Modules\Inventory\Controllers\UnitController::class)
            ->except(['index', 'show']);
    });

    // ── Inventory Transfer — inventory.transfer ────────────────────────────────
    Route::middleware(['permission:inventory.transfer', 'rbac.shadow:inventory.transfer'])->group(function () {
        Route::post('/tenant/inventory/transfer',  [\App\Modules\Inventory\Controllers\InventoryController::class, 'transfer']);
        Route::get('/tenant/inventory/movements',  [\App\Modules\Inventory\Controllers\InventoryController::class, 'movements']);
    });

    // ── Procurement — purchases.receive (Admin + InventoryManager) ─────────────
    Route::middleware(['permission:purchases.receive', 'rbac.shadow:purchases.receive'])->group(function () {
        Route::post('/tenant/purchases/receive',     [\App\Modules\Procurement\Controllers\PurchaseController::class, 'receive']);
        Route::get('/tenant/purchases',              [\App\Modules\Procurement\Controllers\PurchaseController::class, 'index']);
        Route::get('/tenant/purchases/{id}',         [\App\Modules\Procurement\Controllers\PurchaseController::class, 'show']);
        Route::apiResource('tenant/suppliers',       \App\Modules\Procurement\Controllers\SupplierController::class);
    });

    // ── Reports — reports.view (Admin + Accountant) ────────────────────────────
    Route::middleware(['permission:reports.view', 'rbac.shadow:reports.view'])->group(function () {
        Route::get('/tenant/reports/profit-loss', [\App\Modules\Reporting\Controllers\FinancialReportController::class, 'profitAndLoss']);
        Route::get('/tenant/reports/valuation',   [\App\Modules\Reporting\Controllers\FinancialReportController::class, 'valuation']);
    });
});
