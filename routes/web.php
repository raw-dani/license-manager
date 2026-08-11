<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::middleware(['auth', 'role.or:admin,super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::resource('products', ProductController::class)->except(['show']);

    // Licenses
    Route::get('licenses', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('licenses/create', [LicenseController::class, 'create'])->name('licenses.create');
    Route::post('licenses', [LicenseController::class, 'store'])->name('licenses.store');
    Route::get('licenses/generate-key', [LicenseController::class, 'generateKey'])->name('licenses.generate-key');
    Route::get('licenses/{license}/edit', [LicenseController::class, 'edit'])->name('licenses.edit');
    Route::get('licenses/{license}', [LicenseController::class, 'show'])->name('licenses.show');
    Route::put('licenses/{license}', [LicenseController::class, 'update'])->name('licenses.update');
    Route::post('licenses/{license}/suspend', [LicenseController::class, 'suspend'])->name('licenses.suspend');
    Route::post('licenses/{license}/activate', [LicenseController::class, 'activate'])->name('licenses.activate');
    Route::post('licenses/{license}/terminate', [LicenseController::class, 'terminate'])->name('licenses.terminate');
    Route::delete('licenses/{license}', [LicenseController::class, 'destroy'])->name('licenses.destroy');
    Route::delete('licenses/{license}/activations/{activation}', [LicenseController::class, 'destroyActivation'])->name('licenses.activations.destroy');

    // Logs
    Route::get('logs', [LogController::class, 'index'])->name('logs.index');

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('settings/regenerate-api-key', [SettingController::class, 'regenerateApiKey'])->name('settings.regenerate-api-key');

    // Profile
    Route::get('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Users
    Route::resource('users', UserController::class)->except(['show']);
});

require __DIR__.'/auth.php';