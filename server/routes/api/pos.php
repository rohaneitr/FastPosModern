<?php

/**
 * POS (Point of Sale) Routes
 *
 * Covers: checkout, register open/close/status, sales data.
 * All routes: auth:sanctum + subscribed + pos.access permission.
 * Hardware lock enforced on transactional endpoints.
 * RBAC shadow logging enabled on all POS operations.
 *
 * Loaded by RouteServiceProvider under prefix: api/v1
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {

    // ── POS Core Operations — pos.access (Cashier + Admin) ────────────────────
    // Hardware-locked to registered terminal. Shadow logged.
    Route::middleware(['permission:pos.access', 'hardware_lock', 'rbac.shadow:pos.access'])->group(function () {
        Route::post('/tenant/sales/checkout',    [\App\Modules\Sales\Controllers\TransactionController::class, 'checkout'])
            ->middleware('throttle:checkout');

        Route::get('/tenant/registers/status',   [\App\Modules\Sales\Controllers\RegisterController::class, 'status']);
        Route::post('/tenant/registers/open',    [\App\Modules\Sales\Controllers\RegisterController::class, 'open']);
        Route::post('/tenant/registers/close',   [\App\Modules\Sales\Controllers\RegisterController::class, 'close']);
    });

    // ── Sales History & Returns — sales.manage (Cashier + Admin + Accountant) ──
    Route::middleware(['permission:sales.manage', 'rbac.shadow:sales.manage'])->group(function () {
        Route::get('/tenant/sales',               [\App\Modules\Sales\Controllers\SalesController::class, 'index']);
        Route::get('/tenant/sales/{id}',          [\App\Modules\Sales\Controllers\SalesController::class, 'show']);
        Route::post('/tenant/sales/{id}/refund',  [\App\Modules\Sales\Controllers\SalesController::class, 'refund']);
    });

    // ── Advanced Sales (Admin only) ────────────────────────────────────────────
    Route::middleware(['permission:sales.manage', 'rbac.shadow:sales.manage'])->group(function () {
        Route::get('/tenant/sales/advanced',      [\App\Modules\Sales\Controllers\AdvancedSalesController::class, 'index']);
        Route::get('/tenant/sales/daily-summary', [\App\Modules\Sales\Controllers\AdvancedSalesController::class, 'dailySummary']);
    });
});
