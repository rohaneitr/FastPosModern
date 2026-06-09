<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:SuperAdmin'])->group(function () {
    Route::prefix('superadmin')->group(function () {
        Route::post('/licenses/generate', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'generateLicense']);
        Route::get('/overview-stats', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'overviewStats']);
        Route::get('/dashboard-overview', [\App\Modules\SuperAdmin\Controllers\DashboardOverviewController::class, 'getOverview']);
        Route::get('/businesses', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'businesses']);
        Route::post('/businesses', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'storeBusiness']);
        Route::delete('/businesses/{id}', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'destroyBusiness']);
        Route::post('/businesses/{id}/toggle', [\App\Modules\Tenant\Controllers\SuperadminController::class, 'toggleStatus']);
        Route::get('/plans', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'getPlans']);
        Route::post('/plans', [\App\Modules\Tenant\Controllers\SubscriptionController::class, 'storePlan']);
    });
    Route::get('/currencies', [\App\Modules\Tenant\Controllers\SettingsController::class, 'currencies']);
    Route::get('/exchange-rates', [\App\Modules\Tenant\Controllers\SettingsController::class, 'exchangeRates']);
    Route::post('/exchange-rates/update', [\App\Modules\Tenant\Controllers\SettingsController::class, 'updateExchangeRates']);
    Route::post('/exchange-rates/set', [\App\Modules\Tenant\Controllers\SettingsController::class, 'setExchangeRate']);
});
