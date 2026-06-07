<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Domain\IAM\Controllers\AuthController;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/forgot', [\App\Domain\IAM\Controllers\PasswordResetController::class, 'sendResetLink']);
    Route::post('/password/verify-otp', [\App\Domain\IAM\Controllers\PasswordResetController::class, 'verifyOtp']);
    Route::post('/password/reset', [\App\Domain\IAM\Controllers\PasswordResetController::class, 'resetPassword']);
    Route::post('/register/self', [\App\Domain\Tenant\Controllers\RegistrationController::class, 'registerSelf']);
    Route::post('/register/accept-invite', [\App\Domain\IAM\Controllers\InvitationController::class, 'acceptInvite'])->name('api.invites.accept');
    Route::post('/webhooks/stripe', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'handleStripeWebhook']);
    Route::get('/health', \App\Http\Controllers\HealthController::class);
    Route::get('/tenant/resolve/{subdomain}', [\App\Domain\Tenant\Controllers\PublicTenantController::class, 'resolveSubdomain']);
    
    // Device Hardware Binding (Public APIs that rely on License cryptography)
    Route::post('/devices/heartbeat', [\App\Domain\Tenant\Controllers\DeviceHeartbeatController::class, 'heartbeat']);
    Route::post('/devices/status', [\App\Domain\Tenant\Controllers\DeviceHeartbeatController::class, 'status']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // IAM Domain
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        // Profile Settings Domain
        Route::prefix('profile')->group(function () {
            Route::get('/', [\App\Domain\IAM\Controllers\ProfileController::class, 'getProfile']);
            Route::put('/', [\App\Domain\IAM\Controllers\ProfileController::class, 'updateProfile']);
            Route::post('/update', [\App\Domain\IAM\Controllers\ProfileController::class, 'update']);
            Route::post('/password', [\App\Domain\IAM\Controllers\ProfileController::class, 'changePassword']);
            Route::post('/avatar', [\App\Domain\IAM\Controllers\ProfileController::class, 'updateAvatar']);
            Route::get('/activities', [\App\Domain\IAM\Controllers\ProfileController::class, 'getActivities']);
            Route::put('/preferences', [\App\Domain\IAM\Controllers\ProfileController::class, 'updatePreferences']);
            
            // 2FA Routes
            Route::post('/2fa/enable', [\App\Domain\IAM\Controllers\TwoFactorController::class, 'enable']);
            Route::post('/2fa/verify', [\App\Domain\IAM\Controllers\TwoFactorController::class, 'verify']);
            Route::post('/2fa/disable', [\App\Domain\IAM\Controllers\TwoFactorController::class, 'disable']);
            // General Authenticated Routes
            Route::get('/notifications', [\App\Domain\Tenant\Controllers\NotificationController::class, 'index']);
            Route::put('/notifications/read-all', [\App\Domain\Tenant\Controllers\NotificationController::class, 'markAllAsRead']);
            Route::put('/notifications/{id}/read', [\App\Domain\Tenant\Controllers\NotificationController::class, 'markAsRead']);
            Route::get('/announcements', [\App\Domain\Tenant\Controllers\AnnouncementController::class, 'index']);
            
            // Bulk Messaging (SuperAdmin/BusinessAdmin)
            Route::post('/messages/bulk', [\App\Domain\Tenant\Controllers\NotificationController::class, 'sendBulk']);

            // Device Management
            Route::get('/devices', [\App\Domain\IAM\Controllers\DeviceController::class, 'index']);
            Route::put('/devices/{id}/block', [\App\Domain\IAM\Controllers\DeviceController::class, 'block']);
            Route::delete('/devices/{id}', [\App\Domain\IAM\Controllers\DeviceController::class, 'destroy']);

            // Support Tickets
            Route::get('/tickets', [\App\Domain\Support\Controllers\TicketController::class, 'index']);
            Route::post('/tickets', [\App\Domain\Support\Controllers\TicketController::class, 'store']);
            Route::get('/tickets/{id}', [\App\Domain\Support\Controllers\TicketController::class, 'show']);
            Route::post('/tickets/{id}/reply', [\App\Domain\Support\Controllers\TicketController::class, 'reply']);
            Route::put('/tickets/{id}/status', [\App\Domain\Support\Controllers\TicketController::class, 'updateStatus']);
            
        });

        // Global User Setting
        Route::post('/user/language', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateLanguage']);

        // License Activation for Pending Tenants
        Route::post('/tenant/activate-license', [\App\Domain\Tenant\Controllers\LicenseController::class, 'activateTenantLicense']);

        // ---- SUPER ADMIN ONLY ----
        Route::middleware('role:SuperAdmin')->group(function () {
            Route::post('/superadmin/impersonate/{tenant_id}/{user_id?}', [\App\Domain\IAM\Controllers\ImpersonationController::class, 'impersonate']);
            Route::post('/superadmin/branding', [\App\Domain\Tenant\Controllers\SuperAdminSettingsController::class, 'updateBranding']);
            Route::post('/superadmin/announcements', [\App\Domain\Tenant\Controllers\AnnouncementController::class, 'createGlobal']);
            Route::prefix('superadmin')->group(function () {
                Route::get('/monitoring', [\App\Domain\Tenant\Controllers\SuperAdminTelemetryController::class, 'monitoring']);
                Route::get('/audit-logs', [\App\Domain\Tenant\Controllers\SuperAdminTelemetryController::class, 'auditLogs']);
                Route::get('/email-logs', [\App\Domain\Tenant\Controllers\SuperAdminTelemetryController::class, 'emailLogs']);
                Route::get('/licenses', [\App\Domain\Tenant\Controllers\SuperAdminTelemetryController::class, 'licenses']);
                Route::post('/licenses/generate', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'generateLicense']);
                Route::get('/approvals', [\App\Domain\Tenant\Controllers\SuperAdminTelemetryController::class, 'approvals']);
                Route::get('/overview-stats', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'overviewStats']);
                Route::get('/businesses', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'businesses']);
                Route::post('/businesses', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'storeBusiness']);
                Route::delete('/businesses/{id}', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'destroyBusiness']);
                Route::post('/businesses/{id}/toggle', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'toggleStatus']);
                Route::get('/businesses/{id}/export', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'exportBusinessData']);
                Route::put('/businesses/{id}/modules', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'updateModules']);
                Route::post('/businesses/{id}/subscription/renew', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'renewSubscription']);
                Route::post('/businesses/{id}/subscription/override', [\App\Domain\Tenant\Controllers\SuperadminController::class, 'overrideSubscription']);
                Route::get('/plans', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'getPlans']);
                Route::post('/plans', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'storePlan']);
                Route::put('/plans/{id}', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'updatePlan']);
                Route::delete('/plans/{id}', [\App\Domain\Tenant\Controllers\SubscriptionController::class, 'destroyPlan']);

                // SMTP Settings
                Route::get('/smtp-settings', [\App\Domain\Tenant\Controllers\EmailLogController::class, 'getSmtpSettings']);
                Route::post('/smtp-settings', [\App\Domain\Tenant\Controllers\EmailLogController::class, 'saveSmtpSettings']);
                Route::post('/smtp-settings/test', [\App\Domain\Tenant\Controllers\EmailLogController::class, 'testSmtp']);

                // Backups
                Route::get('/backups', [\App\Domain\Tenant\Controllers\BackupController::class, 'index']);
                Route::post('/backups/run', [\App\Domain\Tenant\Controllers\BackupController::class, 'runBackup']);
                Route::post('/backups/download', [\App\Domain\Tenant\Controllers\BackupController::class, 'download']);
            });
            Route::get('/currencies', [\App\Domain\Tenant\Controllers\SettingsController::class, 'currencies']);
            Route::get('/exchange-rates', [\App\Domain\Tenant\Controllers\SettingsController::class, 'exchangeRates']);
            Route::post('/exchange-rates/update', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateExchangeRates']);
            Route::post('/exchange-rates/set', [\App\Domain\Tenant\Controllers\SettingsController::class, 'setExchangeRate']);
        });

        // ---- BUSINESS ADMIN ONLY ----
        Route::middleware(['subscribed'])->group(function () {
            Route::middleware('role:BusinessAdmin')->group(function () {
            Route::post('/business/invites', [\App\Domain\IAM\Controllers\InvitationController::class, 'sendInvite']);
            Route::post('/business/branding', [\App\Domain\Tenant\Controllers\BusinessSettingsController::class, 'updateBranding']);
            Route::post('/business/announcements', [\App\Domain\Tenant\Controllers\AnnouncementController::class, 'createTenant']);
            // HR Domain (Admin)
            Route::get('/hr/employees', [\App\Domain\HR\Controllers\HRController::class, 'employees']);
            Route::put('/hr/employees/{id}/profile', [\App\Domain\HR\Controllers\HRController::class, 'updateEmployeeProfile']);
            
            Route::get('/hr/attendance', [\App\Domain\HR\Controllers\HRController::class, 'getAttendance']);
            Route::put('/hr/attendance/{id}', [\App\Domain\HR\Controllers\HRController::class, 'updateAttendance']);
            
            Route::get('/hr/payrolls', [\App\Domain\HR\Controllers\HRController::class, 'payrolls']);
            Route::post('/hr/payrolls/generate', [\App\Domain\HR\Controllers\HRController::class, 'generatePayroll']);
            Route::post('/hr/payrolls/{id}/pay', [\App\Domain\HR\Controllers\HRController::class, 'payPayroll']);
            
            // Settings Domain
            Route::prefix('settings')->group(function () {
                Route::get('/', [\App\Domain\Tenant\Controllers\SettingsController::class, 'index']);
                Route::post('/business', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateBusiness']);
                Route::get('/branding', [\App\Domain\Tenant\Controllers\SettingsController::class, 'getBranding']);
                Route::put('/branding', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateBranding']);
                
                // Communications
                Route::get('/communications', [\App\Domain\Tenant\Controllers\SettingsController::class, 'getCommunicationSettings']);
                Route::put('/communications', [\App\Domain\Tenant\Controllers\SettingsController::class, 'updateCommunicationSettings']);
                Route::post('/communications/smtp-test', [\App\Domain\Tenant\Controllers\SettingsController::class, 'testSmtpConnection']);
                
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

            // User Management
            Route::apiResource('users', \App\Domain\IAM\Controllers\UserController::class);
        });
        
        // Device Activation (Requires Authentication)
        Route::post('/devices/activate', [\App\Domain\Tenant\Controllers\DeviceHeartbeatController::class, 'activatePosDevice']);
        Route::get('/devices', [\App\Domain\Tenant\Controllers\DeviceHeartbeatController::class, 'getDevices']);
        Route::delete('/devices/{id}', [\App\Domain\Tenant\Controllers\DeviceHeartbeatController::class, 'revokeDevice']);

        // ---- BUSINESS STAFF ----
        
        // Staff Attendance (Any user with a role)
        Route::middleware('role_or_permission:BusinessAdmin|InventoryManager|Cashier')->group(function () {
            Route::post('/hr/attendance/clock-in', [\App\Domain\HR\Controllers\HRController::class, 'clockIn']);
            Route::post('/hr/attendance/clock-out', [\App\Domain\HR\Controllers\HRController::class, 'clockOut']);
        });
        // Catalog Domain
        Route::middleware('role_or_permission:BusinessAdmin|InventoryManager|Cashier')->group(function () {
            Route::get('products/alternatives', [\App\Domain\Catalog\Controllers\ProductController::class, 'genericAlternatives'])->middleware('module:pharmacy');
            Route::get('products/warranty-check', [\App\Domain\Sales\Controllers\RMAController::class, 'warrantyCheck']);
            Route::post('products/print-labels', [\App\Domain\Catalog\Controllers\ProductController::class, 'printLabels']);
            Route::apiResource('products', \App\Domain\Catalog\Controllers\ProductController::class);
            Route::apiResource('categories', \App\Domain\Catalog\Controllers\CategoryController::class);
            Route::apiResource('brands', \App\Domain\Catalog\Controllers\BrandController::class);
        });

        // Inventory Domain
        Route::middleware('role_or_permission:BusinessAdmin|InventoryManager')->group(function () {
            Route::get('/inventory/stock', [\App\Domain\Inventory\Controllers\InventoryController::class, 'stock']);
            Route::post('/inventory/adjust', [\App\Domain\Inventory\Controllers\InventoryController::class, 'adjustStock']);
            Route::post('/inventory/transfer', [\App\Domain\Inventory\Controllers\InventoryController::class, 'transferStock']);
            Route::get('/inventory/low-stock', [\App\Domain\Inventory\Controllers\InventoryController::class, 'lowStock']);

            // Multi-Branch Stock Transfers
            Route::get('/inventory/transfers', [\App\Domain\Inventory\Controllers\StockTransferController::class, 'index']);
            Route::post('/inventory/transfers', [\App\Domain\Inventory\Controllers\StockTransferController::class, 'store']);
            Route::get('/inventory/transfers/{id}', [\App\Domain\Inventory\Controllers\StockTransferController::class, 'show']);
            Route::put('/inventory/transfers/{id}/status', [\App\Domain\Inventory\Controllers\StockTransferController::class, 'updateStatus']);
        });

        // Sales & CRM
        Route::middleware('role_or_permission:BusinessAdmin|Cashier')->group(function () {
            Route::get('/register/status', [\App\Domain\Sales\Controllers\RegisterController::class, 'status']);
            Route::post('/register/open', [\App\Domain\Sales\Controllers\RegisterController::class, 'open']);
            Route::post('/register/close', [\App\Domain\Sales\Controllers\RegisterController::class, 'close']);
            Route::get('/rma', [\App\Domain\Sales\Controllers\RMAController::class, 'index']);
            Route::post('/rma', [\App\Domain\Sales\Controllers\RMAController::class, 'store']);
            Route::put('/rma/{id}/status', [\App\Domain\Sales\Controllers\RMAController::class, 'updateStatus']);
            Route::apiResource('contacts', \App\Domain\CRM\Controllers\ContactController::class);
            Route::get('/sales', [\App\Domain\Sales\Controllers\AdvancedSalesController::class, 'index']);
            Route::post('/sales/{id}/email', [\App\Domain\Sales\Controllers\TransactionController::class, 'sendEmail']);
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
            Route::apiResource('expense-categories', \App\Domain\Accounting\Controllers\ExpenseCategoryController::class);
            Route::get('/reports/dashboard', [\App\Domain\Reporting\Controllers\ReportController::class, 'dashboardKPIs']);
            Route::get('/reports/eod', [\App\Domain\Reporting\Controllers\ReportController::class, 'endOfDayReport']);
            Route::get('/reports/profit-loss', [\App\Domain\Reporting\Controllers\ReportController::class, 'profitLoss']);
            Route::get('/reports/payroll-vs-revenue', [\App\Domain\Reporting\Controllers\ReportController::class, 'payrollVsRevenue']);
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
