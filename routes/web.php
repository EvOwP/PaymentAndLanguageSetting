<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingController;

Route::get('/', function () {
    return redirect()->route('checkout');
});

Route::prefix('admin')->group(function () {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::resource('languages', LanguageController::class);
    Route::post('languages/{language}/set-default', [LanguageController::class, 'setDefault'])->name('languages.setDefault');
    Route::post('languages/{language}/toggle-navbar', [LanguageController::class, 'toggleNavbar'])->name('languages.toggleNavbar');
    Route::resource('gateways', PaymentGatewayController::class);

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
});

Route::get('change-language/{code}', [LanguageController::class, 'changeLanguage'])->name('languages.change');

Route::get('checkout', [PaymentController::class, 'index'])->name('checkout');
Route::post('checkout/process', [PaymentController::class, 'process'])->name('checkout.process');
