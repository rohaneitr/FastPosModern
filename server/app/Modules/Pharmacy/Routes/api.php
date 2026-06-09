<?php

use Illuminate\Support\Facades\Route;

// This will automatically be prefixed with /api/v1 by the ModuleServiceProvider
// and will have 'api', 'auth:sanctum', and 'module.access' middleware applied.
Route::prefix('pharmacy')->name('pharmacy.')->group(function () {
    // Move existing routes here or add new endpoints
    Route::get('/status', function () {
        return response()->json(['status' => 'Pharmacy module active']);
    })->name('status');
});
