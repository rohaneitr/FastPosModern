<?php

use Illuminate\Support\Facades\Route;

/**
 * Procurement Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - suppliers.view    → InventoryManager, Manager, BusinessAdmin, Accountant
 *  - suppliers.create  → InventoryManager, Manager, BusinessAdmin
 *  - suppliers.edit    → InventoryManager, Manager, BusinessAdmin
 *  - suppliers.delete  → BusinessAdmin only (supplier data deletion is high-risk)
 *  - purchases.view    → InventoryManager, Manager, BusinessAdmin, Accountant
 *  - purchases.create  → InventoryManager, Manager, BusinessAdmin
 *  - purchases.edit    → InventoryManager, Manager, BusinessAdmin
 *  - purchases.delete  → BusinessAdmin only (financial record)
 *  - purchases.receive → InventoryManager, Manager, BusinessAdmin (triggers stock receipt)
 *
 * FIX APPLIED: DELETE /suppliers and DELETE /purchases were previously gated
 *   by 'permission:products.manage' — WRONG. products.manage is a catalog permission.
 *   Deleting suppliers/purchases are procurement actions → now correctly gated by
 *   'permission:suppliers.delete' and 'permission:purchases.delete' respectively.
 */
Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_procurement'])->group(function () {

    // ── SUPPLIERS ─────────────────────────────────────────────────────────────
    Route::middleware('permission:suppliers.view')->group(function () {
        Route::apiResource('suppliers', \App\Modules\Procurement\Controllers\SupplierController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:suppliers.create')->group(function () {
        Route::apiResource('suppliers', \App\Modules\Procurement\Controllers\SupplierController::class)->only(['store']);
    });

    Route::middleware('permission:suppliers.edit')->group(function () {
        Route::apiResource('suppliers', \App\Modules\Procurement\Controllers\SupplierController::class)->only(['update']);
    });

    // DELETE SUPPLIER: Requires specific suppliers.delete permission (NOT products.manage — fixed)
    Route::delete('suppliers/{supplier}', [\App\Modules\Procurement\Controllers\SupplierController::class, 'destroy'])
        ->middleware('permission:suppliers.delete');

    // ── PURCHASE ORDERS ───────────────────────────────────────────────────────
    Route::middleware('permission:purchases.view')->group(function () {
        Route::apiResource('purchases', \App\Modules\Procurement\Controllers\PurchaseController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:purchases.create')->group(function () {
        Route::apiResource('purchases', \App\Modules\Procurement\Controllers\PurchaseController::class)->only(['store']);
    });

    Route::middleware('permission:purchases.edit')->group(function () {
        Route::apiResource('purchases', \App\Modules\Procurement\Controllers\PurchaseController::class)->only(['update']);
    });

    // DELETE PURCHASE: Requires specific purchases.delete permission (NOT products.manage — fixed)
    Route::delete('purchases/{purchase}', [\App\Modules\Procurement\Controllers\PurchaseController::class, 'destroy'])
        ->middleware('permission:purchases.delete');

    // RECEIVE STOCK: Triggers inventory layer creation — specific action permission
    Route::post('/purchases/{id}/receive', [\App\Modules\Procurement\Controllers\PurchaseController::class, 'receive'])
        ->middleware('permission:purchases.receive');
});
