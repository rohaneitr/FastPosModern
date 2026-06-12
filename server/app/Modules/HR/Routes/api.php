<?php

use Illuminate\Support\Facades\Route;

/**
 * HR Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - hr.employees.manage → BusinessAdmin only (full employee lifecycle)
 *  - hr.payroll.manage   → BusinessAdmin only (payroll is financial + sensitive)
 *  - users.invite        → BusinessAdmin (send team invitations)
 *  - hr.attendance       → All authenticated staff (Cashier, InventoryManager, Manager, BusinessAdmin)
 *
 * FIX APPLIED: 'role:BusinessAdmin' hardcoded gate removed.
 *   Now uses 'permission:hr.employees.manage' — any role with this permission can manage HR.
 *   This allows a future 'HRManager' custom role to be created WITHOUT code changes.
 *
 * FIX APPLIED: 'role_or_permission:BusinessAdmin|InventoryManager|Cashier' for attendance removed.
 *   Now uses 'permission:hr.attendance' — assigned to all staff roles by default.
 */
Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_hr'])->group(function () {

    // Employee management — HR admin actions
    Route::middleware('permission:hr.employees.manage')->group(function () {
        Route::get('/hr/employees', [\App\Modules\HR\Controllers\HRController::class, 'employees']);
        Route::post('/hr/employees', [\App\Modules\HR\Controllers\HRController::class, 'storeEmployee']);
        Route::put('/hr/employees/{id}', [\App\Modules\HR\Controllers\HRController::class, 'updateEmployee']);
        Route::delete('/hr/employees/{id}', [\App\Modules\HR\Controllers\HRController::class, 'deleteEmployee']);
    });

    // Team invitations — separate permission from employee CRUD
    Route::middleware('permission:users.invite')->group(function () {
        Route::post('/business/invites', [\App\Modules\HR\Controllers\InviteController::class, 'store']);
    });

    // Payroll — financial action, separate permission
    Route::middleware('permission:hr.payroll.manage')->group(function () {
        Route::get('/hr/payrolls', [\App\Modules\HR\Controllers\HRController::class, 'payrolls']);
        Route::post('/hr/payrolls/generate', [\App\Modules\HR\Controllers\HRController::class, 'generatePayroll']);
        Route::post('/hr/payrolls/{id}/pay', [\App\Modules\HR\Controllers\HRController::class, 'payPayroll']);
    });

    // Attendance clock in/out — all authenticated staff members
    Route::middleware('permission:hr.attendance')->group(function () {
        Route::post('/hr/attendance/clock-in', [\App\Modules\HR\Controllers\HRController::class, 'clockIn']);
        Route::post('/hr/attendance/clock-out', [\App\Modules\HR\Controllers\HRController::class, 'clockOut']);
    });
});
