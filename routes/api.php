<?php

use Illuminate\Support\Facades\Route;

// API v1 routes
Route::prefix('v1')->group(function () {
    // Public endpoints (no auth required)
    Route::post('/ping', [App\Http\Controllers\Api\V1\PingController::class, 'ping']);

    // License activation endpoints (protected by API key middleware + rate limit)
    Route::middleware(['api.key', 'throttle:10,1'])->group(function () {
        Route::post('/activate', [App\Http\Controllers\Api\V1\ActivateController::class, 'activate']);
        Route::post('/verify', [App\Http\Controllers\Api\V1\VerifyController::class, 'verify']);
        Route::post('/deactivate', [App\Http\Controllers\Api\V1\DeactivateController::class, 'deactivate']);
        Route::post('/validate', [App\Http\Controllers\Api\V1\ValidateController::class, 'validate']);
        Route::get('/license/{key}', [App\Http\Controllers\Api\V1\LicenseController::class, 'show']);
    });
});