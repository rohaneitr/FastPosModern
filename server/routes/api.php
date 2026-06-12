<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {
    Route::get('/health', \App\Http\Controllers\HealthController::class);
    
    // License Activation (Public endpoint, uses license_key)
    Route::post('/licenses/activate-device', [\App\Modules\Tenant\Controllers\LicenseActivationController::class, 'activateDevice']);

    // Webhooks (SaaS Billing)
    Route::post('/webhooks/stripe', [\App\Modules\Tenant\Controllers\WebhookController::class, 'handleStripeWebhook']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/devices/heartbeat', [\App\Modules\Tenant\Controllers\LicenseActivationController::class, 'heartbeat']);

            // General Authenticated Routes
            // Route::get('/notifications', [\App\Modules\Tenant\Controllers\NotificationController::class, 'index']);
            // Route::put('/notifications/read-all', [\App\Modules\Tenant\Controllers\NotificationController::class, 'markAllAsRead']);
            // Route::put('/notifications/{id}/read', [\App\Modules\Tenant\Controllers\NotificationController::class, 'markAsRead']);
            // Route::get('/announcements', [\App\Modules\Tenant\Controllers\AnnouncementController::class, 'index']);
            
            // Bulk Messaging (SuperAdmin/BusinessAdmin)
            // Route::post('/messages/bulk', [\App\Modules\Tenant\Controllers\NotificationController::class, 'sendBulk']);



            // Support Tickets
            // Route::get('/tickets', [\App\Domain\Support\Controllers\TicketController::class, 'index']);
            // Route::post('/tickets', [\App\Domain\Support\Controllers\TicketController::class, 'store']);
            // Route::get('/tickets/{id}', [\App\Domain\Support\Controllers\TicketController::class, 'show']);
            // Route::post('/tickets/{id}/reply', [\App\Domain\Support\Controllers\TicketController::class, 'reply']);
            // Route::put('/tickets/{id}/status', [\App\Domain\Support\Controllers\TicketController::class, 'updateStatus']);


        // ---- BILLING ACTIONS (requires tenant.billing permission) ----
        Route::middleware(['subscribed'])->group(function () {
            // FIX: was 'role:BusinessAdmin' — now 'permission:tenant.billing'
            Route::middleware('permission:tenant.billing')->group(function () {
                Route::post('/tenant/subscription/change-plan', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'changePlan']);
            });

            // ================================================================
            // POS OPERATIONS — pos.access (Cashier + Admin)
            // Hardware-locked to registered terminal. Shadow logged.
            // ================================================================
            Route::middleware(['permission:pos.access', 'hardware_lock', 'rbac.shadow:pos.access'])->group(function () {
                Route::post('/tenant/sales/checkout', [\App\Modules\Sales\Controllers\TransactionController::class, 'checkout'])->middleware('throttle:checkout');
                Route::get('/tenant/registers/status', [\App\Modules\Sales\Controllers\RegisterController::class, 'status']);
                Route::post('/tenant/registers/open', [\App\Modules\Sales\Controllers\RegisterController::class, 'open']);
                Route::post('/tenant/registers/close', [\App\Modules\Sales\Controllers\RegisterController::class, 'close']);
            });

            // ================================================================
            // SALES DATA & SYNC — sales.manage (Cashier + Admin + Accountant)
            // ================================================================
            Route::middleware(['permission:sales.manage', 'rbac.shadow:sales.manage'])->group(function () {
                Route::get('/sync/pull', [\App\Modules\Tenant\Controllers\SyncController::class, 'pull']);
                Route::post('/sync/push', [\App\Modules\Tenant\Controllers\SyncController::class, 'push']);
            });

            // ================================================================
            // INVENTORY OPERATIONS — inventory.manage (Admin + InventoryManager)
            // Previously exposed to Cashiers via broad role group. Now fixed.
            // Controller-level Gate::authorize also added as defense-in-depth.
            // ================================================================
            Route::middleware(['permission:inventory.manage', 'rbac.shadow:inventory.manage'])->group(function () {
                Route::post('/tenant/inventory/transfer', [\App\Modules\Catalog\Controllers\InventoryController::class, 'transfer']);
            });

            // ================================================================
            // REPORTS — reports.manage (Admin + Accountant)
            // ================================================================
            Route::middleware(['permission:reports.manage', 'rbac.shadow:reports.manage'])->group(function () {
                Route::get('/tenant/reports/profit-loss', [\App\Modules\Reports\Controllers\FinancialReportController::class, 'profitAndLoss']);
                Route::get('/tenant/reports/valuation', [\App\Modules\Reports\Controllers\FinancialReportController::class, 'valuation']);
            });

            // ================================================================
            // PROCUREMENT — products.manage (Admin + InventoryManager)
            // ================================================================
            Route::middleware(['permission:products.manage', 'rbac.shadow:products.manage'])->group(function () {
                Route::post('/tenant/purchases/receive', [\App\Modules\Procurement\Controllers\PurchaseController::class, 'receive']);
            });
        });
    });

    // ---------------------------------------------------
    // MOBILE APP BRIDGE
    // ---------------------------------------------------
    Route::prefix('mobile')->group(function () {
        Route::post('/auth/login', [\App\Modules\IAM\Controllers\AuthController::class, 'login']);
        
        Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
            Route::post('/auth/logout', [\App\Modules\IAM\Controllers\AuthController::class, 'logout']);
            Route::get('/auth/me', [\App\Modules\IAM\Controllers\AuthController::class, 'me']);
            
            // FIX: was 'role_or_permission:BusinessAdmin|Cashier' — now uses specific permissions
            Route::middleware('permission:products.view')->group(function () {
                Route::get('/sync/products', [\App\Modules\Catalog\Controllers\ProductController::class, 'index']);
            });
            Route::middleware('permission:sales.manage')->group(function () {
                Route::get('/sync/pull', [\App\Modules\Tenant\Controllers\SyncController::class, 'pull']);
                Route::post('/sync/push', [\App\Modules\Tenant\Controllers\SyncController::class, 'push']);
            });
        });
    });


});

// Mobile API Gateway
Route::prefix('v1/mobile')->middleware('api')->group(base_path('routes/api/v1/mobile.php'));

// Local Staging UAT & Webhook Simulation
if (app()->environment('local', 'testing')) {
    Route::post('/v1/local-test/mock-webhook', function (\Illuminate\Http\Request $request) {
        $secret = env('WEBHOOK_SECRET', 'my_super_secret');
        
        // Ensure env variable is actually set so controller doesn't fail
        putenv("WEBHOOK_SECRET={$secret}");

        $payload = json_encode([
            'transaction_id' => 'MOCK_' . time() . '_' . rand(100, 999),
            'business_id' => $request->input('business_id', 1),
            'amount' => $request->input('amount', 99.00),
            'months_added' => $request->input('months_added', 1)
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        // Instantiate a fresh Request to pass to the controller
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
