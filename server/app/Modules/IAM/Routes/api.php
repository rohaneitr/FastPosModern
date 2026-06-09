<?php

use Illuminate\Support\Facades\Route;
use App\Modules\IAM\Controllers\AuthController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [\App\Modules\IAM\Controllers\PasswordResetController::class, 'sendResetLink']);
Route::post('/password/verify-otp', [\App\Modules\IAM\Controllers\PasswordResetController::class, 'verifyOtp']);
Route::post('/password/reset', [\App\Modules\IAM\Controllers\PasswordResetController::class, 'resetPassword']);
// Route::post('/register/accept-invite', [\App\Modules\IAM\Controllers\InvitationController::class, 'acceptInvite'])->name('api.invites.accept');

// Protected routes
Route::middleware(['auth:sanctum', 'module.access'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    Route::prefix('profile')->group(function () {
        Route::get('/', [\App\Modules\IAM\Controllers\ProfileController::class, 'getProfile']);
        Route::put('/', [\App\Modules\IAM\Controllers\ProfileController::class, 'updateProfile']);
        Route::post('/password', [\App\Modules\IAM\Controllers\ProfileController::class, 'changePassword']);
        Route::post('/avatar', [\App\Modules\IAM\Controllers\ProfileController::class, 'updateAvatar']);
        Route::get('/activities', [\App\Modules\IAM\Controllers\ProfileController::class, 'getActivities']);
        Route::put('/preferences', [\App\Modules\IAM\Controllers\ProfileController::class, 'updatePreferences']);
        
        // 2FA Routes
        Route::post('/2fa/enable', [\App\Modules\IAM\Controllers\TwoFactorController::class, 'enable']);
        Route::post('/2fa/confirm', [\App\Modules\IAM\Controllers\TwoFactorController::class, 'confirm']);
        Route::post('/2fa/disable', [\App\Modules\IAM\Controllers\TwoFactorController::class, 'disable']);
    });

    // Route::get('/devices', [\App\Modules\IAM\Controllers\DeviceController::class, 'index']);
    // Route::put('/devices/{id}/block', [\App\Modules\IAM\Controllers\DeviceController::class, 'block']);
    // Route::delete('/devices/{id}', [\App\Modules\IAM\Controllers\DeviceController::class, 'destroy']);
    
    Route::middleware(['subscribed', 'role:BusinessAdmin'])->group(function () {
        Route::get('/roles', [\App\Modules\IAM\Controllers\RoleController::class, 'index']);
        Route::post('/roles', [\App\Modules\IAM\Controllers\RoleController::class, 'store']);
        Route::get('/permissions', [\App\Modules\IAM\Controllers\RoleController::class, 'permissions']);
    });
});
