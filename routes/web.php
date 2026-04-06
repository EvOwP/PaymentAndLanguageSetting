<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingController;

Route::get('/', function () {
    return redirect()->route('checkout');
});

use App\Http\Controllers\AuthController;

Route::get('login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('login', [AuthController::class, 'login'])->name('login.post')->middleware('guest');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::resource('languages', LanguageController::class);
    Route::post('languages/{language}/set-default', [LanguageController::class, 'setDefault'])->name('languages.setDefault');
    Route::post('languages/{language}/toggle-navbar', [LanguageController::class, 'toggleNavbar'])->name('languages.toggleNavbar');
    Route::resource('gateways', PaymentGatewayController::class);

    // Payments
    Route::get('payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [\App\Http\Controllers\Admin\PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/refund', [\App\Http\Controllers\Admin\PaymentController::class, 'refund'])->name('payments.refund');
    Route::post('payments/{payment}/approve', [\App\Http\Controllers\Admin\PaymentController::class, 'approveManual'])
        ->name('payments.approve');
    Route::post('payments/{payment}/reject', [\App\Http\Controllers\Admin\PaymentController::class, 'rejectManual'])
        ->name('payments.reject');
    Route::post('payments/{payment}/notes', [\App\Http\Controllers\Admin\PaymentController::class, 'updateNotes'])
        ->name('payments.update-notes');

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
});

Route::get('change-language/{code}', [LanguageController::class, 'changeLanguage'])->name('languages.change');

Route::get('checkout', [PaymentController::class, 'index'])->name('checkout');
Route::post('checkout/process', [PaymentController::class, 'process'])
    ->name('checkout.process')
    ->middleware('throttle:10,1');
