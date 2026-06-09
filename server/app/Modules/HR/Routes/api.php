<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_hr'])->group(function () {
    
    // HR Domain - Admin Only
    Route::middleware('role:BusinessAdmin')->group(function () {
        Route::get('/hr/employees', [\App\Modules\HR\Controllers\HRController::class, 'employees']);
        Route::post('/hr/employees', [\App\Modules\HR\Controllers\HRController::class, 'storeEmployee']);
        Route::put('/hr/employees/{id}', [\App\Modules\HR\Controllers\HRController::class, 'updateEmployee']);
        Route::delete('/hr/employees/{id}', [\App\Modules\HR\Controllers\HRController::class, 'deleteEmployee']);

        Route::get('/hr/payrolls', [\App\Modules\HR\Controllers\HRController::class, 'payrolls']);
        Route::post('/hr/payrolls/generate', [\App\Modules\HR\Controllers\HRController::class, 'generatePayroll']);
        Route::post('/hr/payrolls/{id}/pay', [\App\Modules\HR\Controllers\HRController::class, 'payPayroll']);
    });

    // Staff Attendance (Any user with a role)
    Route::middleware('role_or_permission:BusinessAdmin|InventoryManager|Cashier')->group(function () {
        Route::post('/hr/attendance/clock-in', [\App\Modules\HR\Controllers\HRController::class, 'clockIn']);
        Route::post('/hr/attendance/clock-out', [\App\Modules\HR\Controllers\HRController::class, 'clockOut']);
    });

});
