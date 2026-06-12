<?php

use Illuminate\Support\Facades\Route;
use App\Modules\IAM\Controllers\AuthController;

/**
 * IAM Module Routes — FastPOS Modern
 *
 * RBAC Strategy:
 *  - Public routes: login, password reset (no auth required)
 *  - Authenticated routes: profile, 2FA (any authenticated user)
 *  - roles.manage → BusinessAdmin (can manage roles and see permission list)
 *
 * FIX APPLIED: 'role:BusinessAdmin' hardcoded gate on /roles and /permissions removed.
 *   Now uses 'permission:roles.manage' — allows future custom 'RoleAdmin' role without code change.
 */

// ── PUBLIC ROUTES (no authentication required) ────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [\App\Modules\IAM\Controllers\PasswordResetController::class, 'sendResetLink']);
Route::post('/password/verify-otp', [\App\Modules\IAM\Controllers\PasswordResetController::class, 'verifyOtp']);
Route::post('/password/reset', [\App\Modules\IAM\Controllers\PasswordResetController::class, 'resetPassword']);

// ── PROTECTED ROUTES (all authenticated users) ────────────────────────────────
Route::middleware(['auth:sanctum', 'module.access'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Profile management: any authenticated user manages their own profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [\App\Modules\IAM\Controllers\ProfileController::class, 'getProfile']);
        Route::put('/', [\App\Modules\IAM\Controllers\ProfileController::class, 'updateProfile']);
        Route::post('/password', [\App\Modules\IAM\Controllers\ProfileController::class, 'changePassword']);
        Route::post('/avatar', [\App\Modules\IAM\Controllers\ProfileController::class, 'updateAvatar']);
        Route::get('/activities', [\App\Modules\IAM\Controllers\ProfileController::class, 'getActivities']);
        Route::put('/preferences', [\App\Modules\IAM\Controllers\ProfileController::class, 'updatePreferences']);

        // 2FA: any authenticated user can manage their own 2FA
        Route::post('/2fa/enable', [\App\Modules\IAM\Controllers\TwoFactorController::class, 'enable']);
        Route::post('/2fa/confirm', [\App\Modules\IAM\Controllers\TwoFactorController::class, 'confirm']);
        Route::post('/2fa/disable', [\App\Modules\IAM\Controllers\TwoFactorController::class, 'disable']);
    });

    // Role & permission management — requires explicit roles.manage permission
    // FIX: Was 'role:BusinessAdmin' — now correctly 'permission:roles.manage'
    Route::middleware(['subscribed', 'permission:roles.manage'])->group(function () {
        Route::get('/roles', [\App\Modules\IAM\Controllers\RoleController::class, 'index']);
        Route::post('/roles', [\App\Modules\IAM\Controllers\RoleController::class, 'store']);
        Route::get('/permissions', [\App\Modules\IAM\Controllers\RoleController::class, 'permissions']);
    });
});
