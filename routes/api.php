<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Accept incoming webhooks from any specified gateway 
Route::post('/webhooks/{gatewayName}', [WebhookController::class, 'handle'])->name('webhooks.handle');
