<?php

use App\Http\Controllers\Api\License\LicenseCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('/api/license/callback', [LicenseCallbackController::class, 'handle'])
    ->name('api.license.callback');
