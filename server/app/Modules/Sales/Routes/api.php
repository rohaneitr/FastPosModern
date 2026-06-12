<?php

use Illuminate\Support\Facades\Route;

/**
 * Sales Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - pos.access       → Cashier, Manager, BusinessAdmin (hardware-locked terminal)
 *  - sales.manage     → Cashier, Manager, BusinessAdmin, Accountant (view/process)
 *  - sales.void       → Manager, BusinessAdmin (reversing a completed sale)
 *  - registers.manage → Cashier, Manager, BusinessAdmin (open/close cash drawer)
 */
Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_pos'])->group(function () {

    // Register operations: open/close cash drawer — anyone with register.manage
    Route::middleware('permission:registers.manage')->group(function () {
        Route::get('/register/status', [\App\Modules\Sales\Controllers\RegisterController::class, 'status']);
        Route::post('/register/open', [\App\Modules\Sales\Controllers\RegisterController::class, 'open']);
        Route::post('/register/close', [\App\Modules\Sales\Controllers\RegisterController::class, 'close']);
    });

    // Sales data: view, return history
    Route::middleware('permission:sales.view')->group(function () {
        Route::get('/sales', [\App\Modules\Sales\Controllers\AdvancedSalesController::class, 'index']);
        Route::get('/checkout/held', [\App\Modules\Sales\Controllers\TransactionController::class, 'heldTransactions']);
    });

    // Sales processing: checkout, hold, offline sync — requires sales.manage
    Route::middleware('permission:sales.manage')->group(function () {
        Route::post('/checkout', [\App\Modules\Sales\Controllers\TransactionController::class, 'checkout'])
            ->middleware(\App\Http\Middleware\EnforceIdempotencyGateway::class);

        Route::post('/checkout/hold', [\App\Modules\Sales\Controllers\TransactionController::class, 'holdTransaction']);
        Route::delete('/checkout/held/{id}', [\App\Modules\Sales\Controllers\TransactionController::class, 'deleteHeld']);
        Route::post('/sync/offline-transactions', [\App\Modules\Sales\Controllers\TransactionController::class, 'syncOfflineTransactions']);
        Route::post('/transactions/{id}/convert-to-invoice', [\App\Modules\Sales\Controllers\TransactionController::class, 'convertToInvoice']);
    });

    // Returns: requires sales.void (supervisor-level action)
    Route::middleware('permission:sales.void')->group(function () {
        Route::post('/sales/return', [\App\Modules\Sales\Controllers\AdvancedSalesController::class, 'sellReturn']);
    });
});
