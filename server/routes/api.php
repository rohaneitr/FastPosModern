<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Domain\IAM\Controllers\AuthController;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [\App\Domain\IAM\Controllers\PasswordResetController::class, 'sendResetLink']);
    Route::post('/reset-password', [\App\Domain\IAM\Controllers\PasswordResetController::class, 'resetPassword']);
    Route::post('/webhooks/stripe', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'handleStripeWebhook']);
    Route::get('/health', \App\Http\Controllers\HealthController::class);
    Route::get('/tenant/resolve/{subdomain}', [\App\Domain\Tenant\Controllers\PublicTenantController::class, 'resolveSubdomain']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // IAM Domain
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        // Profile Settings Domain
        Route::prefix('profile')->group(function () {
            Route::get('/', [\App\Domain\IAM\Controllers\ProfileController::class, 'getProfile']);
            Route::put('/', [\App\Domain\IAM\Controllers\ProfileController::class, 'updateProfile']);
            Route::post('/password', [\App\Domain\IAM\Controllers\ProfileController::class, 'changePassword']);
            Route::post('/avatar', [\App\Domain\IAM\Controllers\ProfileController::class, 'updateAvatar']);
            Route::get('/activities', [\App\Domain\IAM\Controllers\ProfileController::class, 'getActivities']);
            Route::put('/preferences', [\App\Domain\IAM\Controllers\ProfileController::class, 'updatePreferences']);
            
            // 2FA Routes
            Route::post('/2fa/enable', [\App\Domain\IAM\Controllers\TwoFactorController::class, 'enable']);
            Route::post('/2fa/confirm', [\App\Domain\IAM\Controllers\TwoFactorController::class, 'confirm']);
            Route::post('/2fa/disable', [\App\Domain\IAM\Controllers\TwoFactorController::class, 'disable']);
        });

        // Global User Setting
        Route::post('/user/language', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateLanguage']);

        // ---- SUPER ADMIN ONLY ----
        Route::middleware('role:SuperAdmin')->group(function () {
            Route::prefix('superadmin')->group(function () {
                Route::get('/businesses', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'businesses']);
                Route::post('/businesses/{id}/toggle', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'toggleStatus']);
                Route::get('/plans', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'getPlans']);
                Route::post('/plans', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'storePlan']);
            });
            Route::get('/currencies', [\App\Domain\Tenant\Controllers\SettingsController::class, 'currencies']);
            Route::get('/exchange-rates', [\App\Domain\Tenant\Controllers\SettingsController::class, 'exchangeRates']);
            Route::post('/exchange-rates/update', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateExchangeRates']);
            Route::post('/exchange-rates/set', [\App\Domain\Tenant\Controllers\SettingsController::class, 'setExchangeRate']);
        });

        // ---- BUSINESS ADMIN ONLY ----
        Route::middleware(['subscribed'])->group(function () {
            Route::middleware('role:BusinessAdmin')->group(function () {
            // HR Domain
            Route::get('/hr/employees', [\App\Domain\HR\Controllers\HRController::class, 'employees']);
            Route::post('/hr/employees', [\App\Domain\HR\Controllers\HRController::class, 'storeEmployee']);
            Route::put('/hr/employees/{id}', [\App\Domain\HR\Controllers\HRController::class, 'updateEmployee']);
            Route::delete('/hr/employees/{id}', [\App\Domain\HR\Controllers\HRController::class, 'deleteEmployee']);

            Route::get('/hr/payrolls', [\App\Domain\HR\Controllers\HRController::class, 'payrolls']);
            Route::post('/hr/payrolls', [\App\Domain\HR\Controllers\HRController::class, 'storePayroll']);
            Route::put('/hr/payrolls/{id}', [\App\Domain\HR\Controllers\HRController::class, 'updatePayroll']);
            Route::delete('/hr/payrolls/{id}', [\App\Domain\HR\Controllers\HRController::class, 'deletePayroll']);
            
            // Settings Domain
            Route::prefix('settings')->group(function () {
                Route::get('/', [\App\Domain\Tenant\Controllers\SettingsController::class, 'index']);
                Route::post('/business', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateBusiness']);
                Route::get('/branding', [\App\Domain\Tenant\Controllers\SettingsController::class, 'getBranding']);
                Route::put('/branding', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateBranding']);
                
                // Subscription
                Route::get('/subscription', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'currentSubscription']);
                Route::post('/subscription/subscribe', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'subscribe']);
                Route::get('/subscription/billing-portal', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'billingPortal']);
                Route::get('/plans', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'getPlans']);
            });

            // Locations & Layouts
            Route::apiResource('locations', \App\Domain\Tenant\Controllers\LocationController::class);
            Route::apiResource('invoice-layouts', \App\Domain\Tenant\Controllers\InvoiceLayoutController::class);

            // Roles
            Route::get('/roles', [\App\Domain\IAM\Controllers\RoleController::class, 'index']);
            Route::post('/roles', [\App\Domain\IAM\Controllers\RoleController::class, 'store']);
            Route::get('/permissions', [\App\Domain\IAM\Controllers\RoleController::class, 'permissions']);
        });

        // ---- BUSINESS STAFF ----
        
        // Catalog Domain
        Route::middleware('role_or_permission:BusinessAdmin|InventoryManager|Cashier')->group(function () {
            Route::post('products/print-labels', [\App\Domain\Catalog\Controllers\ProductController::class, 'printLabels']);
            Route::apiResource('products', \App\Domain\Catalog\Controllers\ProductController::class);
            Route::apiResource('categories', \App\Domain\Catalog\Controllers\CategoryController::class);
        });

        // Inventory Domain
        Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
            Route::get('/inventory/stock', [\App\Domain\Inventory\Controllers\InventoryController::class, 'stock']);
            Route::post('/inventory/adjust', [\App\Domain\Inventory\Controllers\InventoryController::class, 'adjustStock']);
            Route::post('/inventory/transfer', [\App\Domain\Inventory\Controllers\InventoryController::class, 'transferStock']);
            Route::get('/inventory/low-stock', [\App\Domain\Inventory\Controllers\InventoryController::class, 'lowStock']);
        });

        // Sales & CRM
        Route::middleware('role_or_permission:BusinessAdmin|Cashier')->group(function () {
            Route::apiResource('contacts', \App\Domain\CRM\Controllers\ContactController::class);
            Route::get('/sales', [\App\Domain\Sales\Controllers\AdvancedSalesController::class, 'index']);
            Route::post('/sales/return', [\App\Domain\Sales\Controllers\AdvancedSalesController::class, 'sellReturn']);
            Route::post('/checkout', [\App\Domain\Sales\Controllers\TransactionController::class, 'checkout']);
            Route::post('/checkout/hold', [\App\Domain\Sales\Controllers\TransactionController::class, 'holdTransaction']);
            Route::get('/checkout/held', [\App\Domain\Sales\Controllers\TransactionController::class, 'heldTransactions']);
            Route::delete('/checkout/held/{id}', [\App\Domain\Sales\Controllers\TransactionController::class, 'deleteHeld']);
        });

        // Purchases
        Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
            Route::apiResource('purchases', \App\Domain\Purchases\Controllers\PurchaseController::class);
        });

        // Accounting & Reporting
        Route::middleware('role_or_permission:BusinessAdmin|Accountant')->group(function () {
            Route::apiResource('expenses', \App\Domain\Accounting\Controllers\ExpenseController::class);
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
        Route::post('/auth/login', [AuthController::class, 'login']);
        
        Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
            Route::post('/auth/logout', [AuthController::class, 'logout']);
            Route::get('/auth/me', [AuthController::class, 'me']);
            
            Route::middleware('role_or_permission:BusinessAdmin|Cashier')->group(function () {
                Route::get('/sync/products', [\App\Domain\Catalog\Controllers\ProductController::class, 'index']);
                Route::post('/sync/push', [\App\Domain\Sales\Controllers\TransactionController::class, 'syncPush']);
            });
        });
    });
});
