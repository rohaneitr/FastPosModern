<?php

/**
 * Authentication Routes
 *
 * Covers: login, logout, me, refresh, password reset, 2FA.
 * Public: login, password-related endpoints.
 * Protected: logout, me, profile — require auth:sanctum.
 *
 * Loaded by RouteServiceProvider under prefix: api/v1
 */

use Illuminate\Support\Facades\Route;

// ── Public Auth Endpoints ──────────────────────────────────────────────────────
Route::post('/auth/login',  [\App\Modules\IAM\Controllers\AuthController::class, 'login'])
    ->middleware('throttle:auth_gateway');

Route::post('/auth/forgot-password', [\App\Modules\IAM\Controllers\PasswordResetController::class, 'sendResetLink'])
    ->middleware('throttle:auth_gateway');

Route::post('/auth/reset-password',  [\App\Modules\IAM\Controllers\PasswordResetController::class, 'reset'])
    ->middleware('throttle:auth_gateway');

// ── Protected Auth Endpoints ───────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',  [\App\Modules\IAM\Controllers\AuthController::class, 'logout']);
    Route::get('/auth/me',       [\App\Modules\IAM\Controllers\AuthController::class, 'me']);
    Route::post('/auth/refresh', [\App\Modules\IAM\Controllers\AuthController::class, 'refresh']);

    // 2FA
    Route::prefix('auth/2fa')->group(function () {
        Route::post('/enable',   [\App\Modules\IAM\Controllers\TwoFactorController::class, 'enable']);
        Route::post('/disable',  [\App\Modules\IAM\Controllers\TwoFactorController::class, 'disable']);
        Route::post('/verify',   [\App\Modules\IAM\Controllers\TwoFactorController::class, 'verify']);
    });

    // Profile
    Route::prefix('profile')->group(function () {
        Route::get('/',         [\App\Modules\IAM\Controllers\ProfileController::class, 'show']);
        Route::put('/',         [\App\Modules\IAM\Controllers\ProfileController::class, 'update']);
        Route::put('/password', [\App\Modules\IAM\Controllers\ProfileController::class, 'changePassword']);
        Route::post('/avatar',  [\App\Modules\IAM\Controllers\ProfileController::class, 'uploadAvatar']);
    });

    // Roles (IAM admin)
    Route::middleware('permission:iam.manage')->group(function () {
        Route::apiResource('roles', \App\Modules\IAM\Controllers\RoleController::class);
    });
});
