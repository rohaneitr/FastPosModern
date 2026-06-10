<?php

use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register/self', [\App\Modules\Tenant\Controllers\RegistrationController::class, 'registerSelf']);
Route::post('/webhooks/stripe', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'handleStripeWebhook']);
Route::get('/tenant/resolve/{subdomain}', [\App\Modules\Tenant\Controllers\PublicTenantController::class, 'resolveSubdomain']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/user/language', [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateLanguage']);
    Route::post('/tenant/activate-license', [\App\Modules\Tenant\Controllers\LicenseController::class, 'activateTenantLicense']);

    // ---- BUSINESS ADMIN ONLY ----
    Route::middleware(['subscribed'])->group(function () {
        Route::middleware('role:BusinessAdmin')->group(function () {
            // Settings Domain
            Route::prefix('settings')->group(function () {
                Route::get('/', [\App\Modules\Tenant\Controllers\SettingsController::class, 'index']);
                Route::post('/business', [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateBusiness']);
                Route::get('/branding', [\App\Modules\Tenant\Controllers\SettingsController::class, 'getBranding']);
                Route::put('/branding', [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateBranding']);
                
                // Subscription
                Route::get('/subscription', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'currentSubscription']);
                Route::post('/subscription/subscribe', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'subscribe']);
                Route::get('/subscription/billing-portal', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'billingPortal']);
                Route::get('/plans', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'getPlans']);
            });

            // Locations & Layouts
            Route::apiResource('locations', \App\Modules\Tenant\Controllers\LocationController::class);
            Route::apiResource('invoice-layouts', \App\Modules\Tenant\Controllers\InvoiceLayoutController::class);
        });
        
        // API Keys (Requires Authentication and Business Admin)
        Route::middleware('role:BusinessAdmin')->group(function () {
            Route::get('/api-keys', [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'index']);
            Route::post('/api-keys', [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'store']);
            Route::delete('/api-keys/{id}', [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'destroy']);
        });

        // Device Activation (Requires Authentication)
        Route::post('/devices/activate', [\App\Modules\Tenant\Controllers\DeviceHeartbeatController::class, 'activatePosDevice']);
        Route::get('/devices', [\App\Modules\Tenant\Controllers\DeviceHeartbeatController::class, 'getDevices']);
        Route::delete('/devices/{id}', [\App\Modules\Tenant\Controllers\DeviceHeartbeatController::class, 'revokeDevice']);
    });
});
