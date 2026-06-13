<?php

/**
 * Tenant Management Routes
 *
 * Covers: subscription management, device management, sync, settings,
 * license activation, webhooks, and tenant registration flows.
 *
 * Loaded by RouteServiceProvider under prefix: api/v1
 */

use Illuminate\Support\Facades\Route;

// ── Public Tenant Endpoints ────────────────────────────────────────────────────
Route::post('/tenant/register',       [\App\Modules\Tenant\Controllers\RegistrationController::class, 'register']);
Route::get('/plans',                  [\App\Modules\Tenant\Controllers\PublicTenantController::class, 'plans']);
Route::post('/licenses/activate-device', [\App\Modules\Tenant\Controllers\LicenseActivationController::class, 'activateDevice']);

// Webhooks (SaaS Billing) — intentionally unauthenticated; validated by Stripe-Signature HMAC.
// CSRF: not applicable (server-to-server, no browser session cookie).
// Auth:sanctum: excluded — Stripe has no Bearer token. Security = HMAC-SHA256 signature verification.
Route::post('/webhooks/stripe',  [\App\Modules\Tenant\Controllers\StripeWebhookController::class, 'handle']);
Route::post('/webhooks/payment', [\App\Modules\Tenant\Controllers\SubscriptionWebhookController::class, 'handle']);

// ── Authenticated Tenant Endpoints ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Device heartbeat — no subscription gate needed (device health)
    Route::post('/devices/heartbeat', [\App\Modules\Tenant\Controllers\LicenseActivationController::class, 'heartbeat']);

    // Settings
    Route::prefix('tenant/settings')->group(function () {
        Route::get('/',      [\App\Modules\Tenant\Controllers\SettingsController::class, 'index']);
        Route::put('/',      [\App\Modules\Tenant\Controllers\SettingsController::class, 'update']);
        Route::put('/smtp',  [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateSmtp']);
    });

    // Locations
    Route::apiResource('tenant/locations', \App\Modules\Tenant\Controllers\LocationController::class)
        ->middleware('permission:tenant.manage');

    // Subscription Management — requires tenant.billing permission
    Route::middleware(['subscribed', 'permission:tenant.billing'])->group(function () {
        Route::post('/tenant/subscription/change-plan', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'changePlan']);
    });

    // API Key Management
    Route::prefix('tenant/api-keys')->middleware('permission:tenant.manage')->group(function () {
        Route::get('/',           [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'index']);
        Route::post('/',          [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'store']);
        Route::delete('/{keyId}', [\App\Modules\Tenant\Controllers\ApiKeyController::class, 'destroy']);
    });

    // Invoice Layout
    Route::apiResource('tenant/invoice-layouts', \App\Modules\Tenant\Controllers\InvoiceLayoutController::class)
        ->middleware('permission:tenant.manage');

    // Data Sync (requires subscription + sales.manage)
    // throttle:pos = 500/min — POS tablets sync large offline transaction batches.
    Route::middleware(['subscribed', 'permission:sales.manage', 'rbac.shadow:sales.manage', 'throttle:pos'])->group(function () {
        Route::get('/sync/pull',  [\App\Modules\Tenant\Controllers\SyncController::class, 'pull']);
        Route::post('/sync/push', [\App\Modules\Tenant\Controllers\SyncController::class, 'push']);
    });

    // License & Device Management (tenant-level)
    Route::prefix('tenant/licenses')->middleware('permission:tenant.manage')->group(function () {
        Route::get('/',                       [\App\Modules\Tenant\Controllers\LicenseController::class, 'index']);
        Route::get('/{id}/devices',           [\App\Modules\Tenant\Controllers\LicenseController::class, 'devices']);
        Route::delete('/devices/{deviceId}',  [\App\Modules\Tenant\Controllers\LicenseController::class, 'revokeDevice']);
    });

    // ── Phase 9: Audit Trail ────────────────────────────────────────────────────
    // Requires subscribed + tenant.manage permission.
    // Tenant isolation is guaranteed by Activity model's global scope (no manual where() needed).
    // SuperAdmins see all tenants' logs; Tenant users see only their own business_id rows.
    Route::middleware(['subscribed', 'permission:tenant.manage'])->prefix('audit-logs')->group(function () {
        // Paginated, filterable log list
        // ?log_name=sales.Sale &event=updated &causer_id=5 &date_from=2026-06-01 &per_page=25
        Route::get('/', [\App\Modules\Tenant\Controllers\AuditLogController::class, 'index'])
            ->name('audit-logs.index');

        // Full history for a specific record: GET /audit-logs/transactions/42
        Route::get('/{subjectType}/{subjectId}', [\App\Modules\Tenant\Controllers\AuditLogController::class, 'show'])
            ->where(['subjectType' => '[a-z_]+', 'subjectId' => '[0-9]+'])
            ->name('audit-logs.show');
    });
});
