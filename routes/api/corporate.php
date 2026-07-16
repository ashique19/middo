<?php

use App\Http\Controllers\Api\Corporate\CorporateMobileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Corporate Mobile API (Flutter app)
|--------------------------------------------------------------------------
*/

Route::prefix('corporate')->group(function () {
    Route::post('/login', [CorporateMobileController::class, 'login']);

    Route::middleware(['auth:sanctum', 'role:corporate'])->group(function () {
        Route::post('/logout', [CorporateMobileController::class, 'logout']);
        Route::get('/me', [CorporateMobileController::class, 'me']);
        Route::get('/dashboard', [CorporateMobileController::class, 'dashboard']);
        Route::get('/menu', [CorporateMobileController::class, 'menu']);
        Route::get('/orders/scheduled', [CorporateMobileController::class, 'scheduled']);
        Route::get('/orders/history', [CorporateMobileController::class, 'history']);
        Route::post('/orders', [CorporateMobileController::class, 'placeOrder']);
        Route::get('/orders/{order}/track', [CorporateMobileController::class, 'track']);
        Route::get('/orders/{order}/support', [CorporateMobileController::class, 'supportThread']);
        Route::post('/orders/{order}/support', [CorporateMobileController::class, 'supportMessage']);
        Route::post('/wallet/top-up', [CorporateMobileController::class, 'topUp']);
    });
});
