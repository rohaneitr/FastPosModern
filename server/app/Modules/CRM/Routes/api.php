<?php

use Illuminate\Support\Facades\Route;

/**
 * CRM Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - contacts.view  → Cashier (needs to look up customers at POS), Manager, BusinessAdmin, Accountant
 *  - contacts.create → Cashier (can register walk-in customers), Manager, BusinessAdmin
 *  - contacts.edit   → Manager, BusinessAdmin
 *  - contacts.delete → BusinessAdmin only (data integrity risk)
 *
 * FIX APPLIED: DELETE /contacts was previously gated by 'permission:tenant.manage' — WRONG.
 *   tenant.manage is for business settings (branding, locations, billing).
 *   Deleting a contact is a CRM action → now correctly gated by 'permission:contacts.delete'.
 */
Route::middleware(['auth:sanctum', 'subscribed', 'module.access:core_crm'])->group(function () {

    // READ + CREATE: Cashiers can look up and create customers at POS
    Route::middleware('permission:contacts.view')->group(function () {
        Route::apiResource('contacts', \App\Modules\CRM\Controllers\ContactController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:contacts.create')->group(function () {
        Route::apiResource('contacts', \App\Modules\CRM\Controllers\ContactController::class)->only(['store']);
    });

    Route::middleware('permission:contacts.edit')->group(function () {
        Route::apiResource('contacts', \App\Modules\CRM\Controllers\ContactController::class)->only(['update']);
    });

    // DELETE: Requires specific contacts.delete permission (NOT tenant.manage — fixed)
    Route::delete('contacts/{contact}', [\App\Modules\CRM\Controllers\ContactController::class, 'destroy'])
        ->middleware('permission:contacts.delete');

    // Bulk messaging: Requires tenant.manage (admin-level broadcast)
    Route::post('/messages/bulk', [\App\Modules\CRM\Controllers\MessageController::class, 'sendBulk'])
        ->middleware('permission:tenant.manage');
});

// Customer Self-Service Portal (uses token ability, not role-based)
Route::middleware(['auth:sanctum', 'abilities:customer:read-own-data', 'module.access:core_crm'])->group(function () {
    Route::get('/customer/dashboard-metrics', [\App\Modules\CRM\Controllers\CustomerPortalController::class, 'dashboardMetrics']);
});
