<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_pos'])->group(function () {
    Route::middleware('role_or_permission:BusinessAdmin|Cashier')->group(function () {
        Route::get('/register/status', [\App\Modules\Sales\Controllers\RegisterController::class, 'status']);
        Route::post('/register/open', [\App\Modules\Sales\Controllers\RegisterController::class, 'open']);
        Route::post('/register/close', [\App\Modules\Sales\Controllers\RegisterController::class, 'close']);
        
        Route::get('/sales', [\App\Modules\Sales\Controllers\AdvancedSalesController::class, 'index']);
        Route::post('/sales/return', [\App\Modules\Sales\Controllers\AdvancedSalesController::class, 'sellReturn']);
        
        Route::post('/checkout', [\App\Modules\Sales\Controllers\TransactionController::class, 'checkout'])
            ->middleware(\App\Http\Middleware\EnforceIdempotencyGateway::class);
            
        Route::post('/transactions/{id}/convert-to-invoice', [\App\Modules\Sales\Controllers\TransactionController::class, 'convertToInvoice']);
        Route::post('/checkout/hold', [\App\Modules\Sales\Controllers\TransactionController::class, 'holdTransaction']);
        Route::get('/checkout/held', [\App\Modules\Sales\Controllers\TransactionController::class, 'heldTransactions']);
        Route::delete('/checkout/held/{id}', [\App\Modules\Sales\Controllers\TransactionController::class, 'deleteHeld']);
    });
});
