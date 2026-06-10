<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:SuperAdmin'])->group(function () {
    Route::prefix('superadmin')->group(function () {
        Route::post('/businesses/{id}/regenerate-license', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'generateLicense']);
        Route::get('/businesses/{id}/devices', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'getBusinessDevices']);
        Route::patch('/devices/{device_id}/revoke', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'revokeSingleDevice']);
        
        Route::get('/licenses', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'getLicenses']);
        Route::put('/licenses/{id}/toggle-status', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'toggleLicenseStatus']);
        
        Route::get('/overview-stats', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'overviewStats']);
        Route::get('/dashboard-overview', [\App\Modules\SuperAdmin\Controllers\DashboardOverviewController::class, 'getOverview']);
        Route::get('/monitoring', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'monitoring']);
        
        Route::get('/backups', [\App\Modules\SuperAdmin\Controllers\BackupController::class, 'index']);
        Route::post('/backups/run', [\App\Modules\SuperAdmin\Controllers\BackupController::class, 'run']);
        Route::post('/backups/download', [\App\Modules\SuperAdmin\Controllers\BackupController::class, 'download']);
        Route::post('/backups/upload', [\App\Modules\SuperAdmin\Controllers\BackupController::class, 'upload']);
        Route::post('/backups/restore', [\App\Modules\SuperAdmin\Controllers\BackupController::class, 'restore']);

        Route::post('/system/maintenance', [\App\Modules\SuperAdmin\Controllers\SystemController::class, 'toggleMaintenance']);
        Route::post('/announcements', [\App\Modules\SuperAdmin\Controllers\SystemController::class, 'broadcastAnnouncement']);

        Route::get('/settings', [\App\Modules\SuperAdmin\Controllers\GlobalSettingsController::class, 'index']);
        Route::post('/settings', [\App\Modules\SuperAdmin\Controllers\GlobalSettingsController::class, 'update']);
        Route::post('/settings/test-smtp', [\App\Modules\SuperAdmin\Controllers\GlobalSettingsController::class, 'testSmtp']);

        Route::get('/audit-logs', [\App\Modules\SuperAdmin\Controllers\AuditLogController::class, 'index']);
        Route::get('/email-logs', [\App\Modules\SuperAdmin\Controllers\EmailLogController::class, 'index']);
        Route::get('/email-logs/stats', [\App\Modules\SuperAdmin\Controllers\EmailLogController::class, 'stats']);

        Route::get('/tickets', [\App\Modules\SuperAdmin\Controllers\TicketManagementController::class, 'index']);
        Route::get('/tickets/{id}', [\App\Modules\SuperAdmin\Controllers\TicketManagementController::class, 'show']);
        Route::post('/tickets/{id}/reply', [\App\Modules\SuperAdmin\Controllers\TicketManagementController::class, 'reply']);
        Route::patch('/tickets/{id}/status', [\App\Modules\SuperAdmin\Controllers\TicketManagementController::class, 'updateStatus']);

        Route::get('/businesses', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'businesses']);
        Route::post('/businesses', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'storeBusiness']);
        Route::delete('/businesses/{id}', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'destroyBusiness']);
        Route::post('/businesses/{id}/toggle', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'toggleStatus']);
        Route::post('/businesses/{id}/modules', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'updateModules']);
        
        Route::post('/businesses/{id}/subscription/renew', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'renewSubscription']);
        Route::post('/businesses/{id}/subscription/override', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'overrideSubscription']);
        
        Route::post('/impersonate/{id}', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'impersonate']);

        Route::get('/tenant-requests', [\App\Modules\Tenant\Controllers\TenantApprovalController::class, 'index']);
        Route::post('/tenant-requests/{id}/approve', [\App\Modules\Tenant\Controllers\TenantApprovalController::class, 'approve']);
        Route::post('/tenant-requests/{id}/reject', [\App\Modules\Tenant\Controllers\TenantApprovalController::class, 'reject']);

        Route::get('/plans', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'getPlans']);
        Route::post('/plans', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'storePlan']);
        Route::put('/plans/{id}', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'updatePlan']);
        Route::delete('/plans/{id}', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'destroyPlan']);
        
        Route::post('/subscriptions/{id}/renew', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'renew']);
        Route::patch('/subscriptions/{id}/status', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'overrideStatus']);
        Route::patch('/subscriptions/{id}/capabilities', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'updateCapabilities']);
        
        Route::get('/system-modules', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'getSystemModules']);
    });
    Route::get('/currencies', [\App\Modules\Tenant\Controllers\SettingsController::class, 'currencies']);
    Route::get('/exchange-rates', [\App\Modules\Tenant\Controllers\SettingsController::class, 'exchangeRates']);
    Route::post('/exchange-rates/update', [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateExchangeRates']);
    Route::post('/exchange-rates/set', [\App\Modules\Tenant\Controllers\SettingsController::class, 'setExchangeRate']);
});

Route::get('/system/announcements', [\App\Modules\SuperAdmin\Controllers\SystemController::class, 'getAnnouncements']);
