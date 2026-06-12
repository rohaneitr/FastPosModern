<?php

use Illuminate\Support\Facades\Route;

/**
 * Tenant Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - Public routes: self-registration, subdomain resolution (no auth)
 *  - tenant.manage  → BusinessAdmin (settings, branding, locations, invoice layouts)
 *  - tenant.billing → BusinessAdmin (subscription management, plan changes)
 *  - tenant.devices → BusinessAdmin (device activation and revocation)
 *
 * FIX APPLIED: Multiple 'role:BusinessAdmin' hardcoded gates removed.
 *   Replaced with specific permission gates matching the action's intent:
 *   - Settings/locations → 'permission:tenant.manage'
 *   - Subscription/billing → 'permission:tenant.billing'
 *   - Device management → 'permission:tenant.devices'
 *   - API keys (infrastructure-level) → 'permission:tenant.manage'
 */

// ── PUBLIC ROUTES ─────────────────────────────────────────────────────────────
Route::post('/register/self', [\App\Modules\Tenant\Controllers\RegistrationController::class, 'registerSelf']);
Route::post('/webhooks/stripe', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'handleStripeWebhook']);
Route::get('/tenant/resolve/{subdomain}', [\App\Modules\Tenant\Controllers\PublicTenantController::class, 'resolveSubdomain']);

// ── PROTECTED ROUTES ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Available to all authenticated users
    Route::post('/user/language', [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateLanguage']);
    Route::post('/tenant/activate-license', [\App\Modules\Tenant\Controllers\LicenseController::class, 'activateTenantLicense']);

    Route::middleware('subscribed')->group(function () {

        // ── TENANT SETTINGS & CONFIGURATION ─────────────────────────────────
        // FIX: was 'role:BusinessAdmin' — now 'permission:tenant.manage'
        Route::middleware('permission:tenant.manage')->group(function () {
            Route::prefix('settings')->group(function () {
                Route::get('/', [\App\Modules\Tenant\Controllers\SettingsController::class, 'index']);
                Route::post('/business', [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateBusiness']);
                Route::get('/branding', [\App\Modules\Tenant\Controllers\SettingsController::class, 'getBranding']);
                Route::put('/branding', [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateBranding']);
            });

            // Locations & Invoice Layouts
            Route::apiResource('locations', \App\Modules\Tenant\Controllers\LocationController::class);
            Route::apiResource('invoice-layouts', \App\Modules\Tenant\Controllers\InvoiceLayoutController::class);
        });

        // ── BILLING & SUBSCRIPTION ───────────────────────────────────────────
        // FIX: was 'role:BusinessAdmin' — now 'permission:tenant.billing'
        Route::middleware('permission:tenant.billing')->group(function () {
            Route::prefix('settings')->group(function () {
                Route::get('/subscription', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'currentSubscription']);
                Route::post('/subscription/subscribe', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'subscribe']);
                Route::get('/subscription/billing-portal', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'billingPortal']);
                Route::get('/plans', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'getPlans']);
            });
        });

        // ── API KEYS (infrastructure-level — tenant.manage) ──────────────────
        // FIX: was second 'role:BusinessAdmin' block — now 'permission:tenant.manage'
        Route::middleware('permission:tenant.manage')->group(function () {
            Route::get('/api-keys', [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'index']);
            Route::post('/api-keys', [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'store']);
            Route::delete('/api-keys/{id}', [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'destroy']);
        });

        // ── DEVICE MANAGEMENT ────────────────────────────────────────────────
        // FIX: was unguarded (only auth+subscribed) — now 'permission:tenant.devices'
        Route::middleware('permission:tenant.devices')->group(function () {
            Route::post('/devices/activate', [\App\Modules\Tenant\Controllers\DeviceHeartbeatController::class, 'activatePosDevice']);
            Route::get('/devices', [\App\Modules\Tenant\Controllers\DeviceHeartbeatController::class, 'getDevices']);
            Route::delete('/devices/{id}', [\App\Modules\Tenant\Controllers\DeviceHeartbeatController::class, 'revokeDevice']);
        });
    });
});
