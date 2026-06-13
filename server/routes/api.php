<?php

/**
 * routes/api.php — FastPOS API Route Orchestrator
 *
 * This file is the single entry point registered in bootstrap/app.php.
 * It does NOT define routes directly. Instead, it delegates to modular
 * route files under routes/api/ grouped under the /api/v1 prefix.
 *
 * Structure:
 *   routes/api/auth.php       — Login, logout, password reset, 2FA, profile
 *   routes/api/tenant.php     — Subscription, settings, sync, license, webhooks
 *   routes/api/pos.php        — Checkout, register, sales
 *   routes/api/inventory.php  — Products, catalog, transfers, procurement, reports
 *
 * Module-level routes (app/Modules/{module}/Routes/api.php) are auto-loaded by
 * ModuleServiceProvider under the same api/v1 prefix.
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │  ADD NEW ROUTE GROUPS:                                              │
 * │  1. Create  routes/api/{module}.php                                 │
 * │  2. Add a single  require __DIR__ . '/api/{module}.php'  below.    │
 * └─────────────────────────────────────────────────────────────────────┘
 */

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Health Check (unauthenticated, load-balancer safe) ─────────────────────
    Route::get('/health', \App\Http\Controllers\HealthController::class);

    // ── Modular Route Files ────────────────────────────────────────────────────
    require __DIR__ . '/api/auth.php';
    require __DIR__ . '/api/tenant.php';
    require __DIR__ . '/api/pos.php';
    require __DIR__ . '/api/inventory.php';

    // ── Mobile API Bridge (separate v1/mobile sub-prefix) ──────────────────────
    Route::prefix('mobile')->group(function () {
        Route::post('/auth/login', [\App\Modules\IAM\Controllers\AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
            Route::post('/auth/logout', [\App\Modules\IAM\Controllers\AuthController::class, 'logout']);
            Route::get('/auth/me',      [\App\Modules\IAM\Controllers\AuthController::class, 'me']);

            Route::middleware('permission:products.view')->group(function () {
                Route::get('/sync/products', [\App\Modules\Catalog\Controllers\ProductController::class, 'index']);
            });

            Route::middleware('permission:sales.manage')->group(function () {
                Route::get('/sync/pull',  [\App\Modules\Tenant\Controllers\SyncController::class, 'pull']);
                Route::post('/sync/push', [\App\Modules\Tenant\Controllers\SyncController::class, 'push']);
            });
        });
    });

    // ── Local / Testing Endpoints (never exposed in production) ───────────────
    if (app()->environment('local', 'testing')) {
        Route::post('/local-test/mock-webhook', function (\Illuminate\Http\Request $request) {
            $secret  = env('WEBHOOK_SECRET', 'my_super_secret');
            putenv("WEBHOOK_SECRET={$secret}");

            $payload = json_encode([
                'transaction_id' => 'MOCK_' . time() . '_' . rand(100, 999),
                'business_id'    => $request->input('business_id', 1),
                'amount'         => $request->input('amount', 99.00),
                'months_added'   => $request->input('months_added', 1),
            ]);

            $signature   = hash_hmac('sha256', $payload, $secret);
            $mockRequest = \Illuminate\Http\Request::create(
                '/api/v1/webhooks/payment',
                'POST',
                [], [], [],
                ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
                $payload
            );

            $controller = app()->make(\App\Modules\Tenant\Controllers\SubscriptionWebhookController::class);
            return $controller->handle($mockRequest);
        });
    }
});

// ── Mobile API Gateway (legacy path, kept for backward-compat) ─────────────────
// New mobile routes SHOULD use the v1/mobile block above.
// This shim loads the enhanced mobile route file that ships with the mobile module.
Route::prefix('v1/mobile')->middleware('api')->group(base_path('routes/api/v1/mobile.php'));
