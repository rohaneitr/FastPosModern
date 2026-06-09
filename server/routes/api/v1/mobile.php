<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileTelemetryController;

Route::post('/auth/login', [MobileAuthController::class, 'login']);

Route::middleware(['auth:sanctum', \App\Http\Middleware\EnforceMobileDeviceFingerprint::class, 'throttle:mobile_pulse'])->group(function () {
    Route::get('/telemetry/pulse', [MobileTelemetryController::class, 'getPulse']);
});
