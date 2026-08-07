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
    Route::post('/register/send-otp', [CorporateMobileController::class, 'sendSignupOtp']);
    Route::post('/register', [CorporateMobileController::class, 'register']);
    Route::post('/forgot-password', [CorporateMobileController::class, 'forgotPassword']);
    Route::post('/reset-password', [CorporateMobileController::class, 'resetPassword']);
    Route::get('/locations', [CorporateMobileController::class, 'locations']);

    Route::middleware(['auth:sanctum', 'role:corporate'])->group(function () {
        Route::post('/logout', [CorporateMobileController::class, 'logout']);
        Route::get('/me', [CorporateMobileController::class, 'me']);
        Route::patch('/profile', [CorporateMobileController::class, 'updateProfile']);
        Route::post('/change-password', [CorporateMobileController::class, 'changePassword']);
        Route::post('/device-tokens', [CorporateMobileController::class, 'registerDeviceToken']);
        Route::delete('/device-tokens', [CorporateMobileController::class, 'unregisterDeviceToken']);
        Route::get('/dashboard', [CorporateMobileController::class, 'dashboard']);
        Route::get('/menu', [CorporateMobileController::class, 'menu']);
        Route::get('/packages', [CorporateMobileController::class, 'packages']);
        Route::post('/packages/send-otp', [CorporateMobileController::class, 'sendPackageOtp']);
        Route::get('/packages/{package}', [CorporateMobileController::class, 'packageShow']);
        Route::post('/packages/{package}/quote', [CorporateMobileController::class, 'packageQuote']);
        Route::post('/packages/{package}/subscribe', [CorporateMobileController::class, 'subscribePackage']);
        Route::get('/subscriptions', [CorporateMobileController::class, 'myPackages']);
        Route::get('/subscriptions/{subscription}', [CorporateMobileController::class, 'myPackageShow']);
        Route::post('/orders/{order}/skip-package-day', [CorporateMobileController::class, 'skipPackageDay']);
        Route::post('/orders/{order}/request-cancel-package-day', [CorporateMobileController::class, 'requestCancelPackageDay']);
        Route::get('/orders/scheduled', [CorporateMobileController::class, 'scheduled']);
        Route::get('/orders/history', [CorporateMobileController::class, 'history']);
        Route::post('/orders/send-otp', [CorporateMobileController::class, 'sendOrderOtp']);
        Route::post('/orders/gateway-prepay', [CorporateMobileController::class, 'createGatewayPrepay']);
        Route::post('/orders', [CorporateMobileController::class, 'placeOrder']);
        Route::patch('/orders/{order}', [CorporateMobileController::class, 'updateOrder']);
        Route::delete('/orders/{order}', [CorporateMobileController::class, 'cancelOrder']);
        Route::get('/orders/{order}/track', [CorporateMobileController::class, 'track']);
        Route::get('/orders/{order}/support', [CorporateMobileController::class, 'supportThread']);
        Route::post('/orders/{order}/support', [CorporateMobileController::class, 'supportMessage']);
        Route::post('/wallet/top-up', [CorporateMobileController::class, 'topUp']);
        Route::get('/wallet/transactions', [CorporateMobileController::class, 'walletTransactions']);
        Route::get('/boxes', [CorporateMobileController::class, 'boxes']);
        Route::post('/boxes/{box}/ready-for-pickup', [CorporateMobileController::class, 'markBoxReadyForPickup']);
    });
});
