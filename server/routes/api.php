<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {
    Route::get('/health', \App\Http\Controllers\HealthController::class);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

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


        // ---- BUSINESS ADMIN ONLY ----
        Route::middleware(['subscribed'])->group(function () {
            Route::middleware('role:BusinessAdmin')->group(function () {

        });
        // ---- BUSINESS STAFF ----
        



            // Analytics Domain
            Route::prefix('analytics')->group(function () {
                Route::get('/consolidated-overview', [\App\Domain\Analytics\Controllers\UnifiedAnalyticsController::class, 'getConsolidatedOverview']);
            });
        Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
            // Bulk Data Migration & Imports
            Route::post('/data-migration/import/products', [\App\Http\Controllers\Api\V1\DataMigration\ImportController::class, 'importProducts']);
            Route::get('/data-migration/status/{id}', [\App\Http\Controllers\Api\V1\DataMigration\ImportController::class, 'getStatus']);
        });

        // Sales & CRM
        Route::middleware('role_or_permission:BusinessAdmin|Cashier')->group(function () {

            // Sync Engine (Web & Mobile Offline)
            Route::get('/sync/pull', [\App\Modules\Tenant\Controllers\SyncController::class, 'pull']);
            Route::post('/sync/push', [\App\Modules\Tenant\Controllers\SyncController::class, 'push']);
        });



        // Accounting & Reporting
        Route::middleware('role_or_permission:BusinessAdmin')->group(function () {
            Route::get('/analytics/overview', [\App\Http\Controllers\Api\V1\Analytics\AnalyticsController::class, 'overview']);
        });
        Route::middleware('role_or_permission:BusinessAdmin|Accountant')->group(function () {

            Route::get('/reports/dashboard', [\App\Domain\Reporting\Controllers\ReportController::class, 'dashboardKPIs']);
            Route::get('/reports/profit-loss', [\App\Domain\Reporting\Controllers\ReportController::class, 'profitLoss']);
            Route::get('/reports/sales', [\App\Domain\Reporting\Controllers\ReportController::class, 'salesReport']);
            Route::get('/reports/sales/export', [\App\Domain\Reporting\Controllers\ReportController::class, 'exportSales']);
            Route::get('/invoices/{id}', [\App\Domain\Reporting\Controllers\InvoiceController::class, 'show']);
            Route::get('/invoices/{id}/print', [\App\Domain\Reporting\Controllers\InvoiceController::class, 'printView']);
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
            
            Route::middleware('role_or_permission:BusinessAdmin|Cashier')->group(function () {
                Route::get('/sync/products', [\App\Domain\Catalog\Controllers\ProductController::class, 'index']);
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
