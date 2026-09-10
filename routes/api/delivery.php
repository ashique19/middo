<?php

use App\Http\Controllers\Api\Delivery\DeliveryMobileController;
use App\Support\DeliveryPermissions;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery Mobile API (Flutter / Android app)
|--------------------------------------------------------------------------
| Contract: docs/delivery-mobile-api-contract.md
*/

Route::prefix('delivery')->group(function () {
    Route::post('/login', [DeliveryMobileController::class, 'login']);

    Route::middleware(['auth:sanctum', 'role:delivery'])->group(function () {
        Route::post('/logout', [DeliveryMobileController::class, 'logout']);
        Route::get('/me', [DeliveryMobileController::class, 'me']);
        Route::post('/change-password', [DeliveryMobileController::class, 'changePassword']);
        Route::post('/device-tokens', [DeliveryMobileController::class, 'registerDeviceToken']);
        Route::delete('/device-tokens', [DeliveryMobileController::class, 'unregisterDeviceToken']);

        Route::middleware('permission:'.DeliveryPermissions::DASHBOARD)->group(function () {
            Route::get('/dashboard', [DeliveryMobileController::class, 'dashboard']);
            Route::post('/shift', [DeliveryMobileController::class, 'setShift']);
        });

        Route::middleware('permission:'.DeliveryPermissions::ALERTS)->group(function () {
            Route::get('/alerts', [DeliveryMobileController::class, 'alerts']);
            Route::patch('/alerts/{id}/read', [DeliveryMobileController::class, 'markAlertRead']);
            Route::patch('/alerts/read-all', [DeliveryMobileController::class, 'markAllAlertsRead']);
        });

        Route::middleware('permission:'.DeliveryPermissions::RUNS)->group(function () {
            Route::get('/runs/history', [DeliveryMobileController::class, 'runsHistory']);
            Route::get('/runs', [DeliveryMobileController::class, 'runs']);
            Route::get('/runs/{id}', [DeliveryMobileController::class, 'showRun']);
            Route::post('/runs/{id}/pickup', [DeliveryMobileController::class, 'pickupRun']);
            Route::post('/runs/{id}/send-delivery-otp', [DeliveryMobileController::class, 'sendDeliveryOtp']);
            Route::post('/runs/{id}/deliver', [DeliveryMobileController::class, 'deliverRun']);

            Route::get('/custom-runs', [DeliveryMobileController::class, 'customRuns']);
            Route::post('/custom-runs/{id}/start', [DeliveryMobileController::class, 'startCustomRun']);
            Route::post('/custom-runs/{id}/complete', [DeliveryMobileController::class, 'completeCustomRun']);
        });

        Route::middleware('permission:'.DeliveryPermissions::BOXES)->group(function () {
            Route::get('/boxes/pending', [DeliveryMobileController::class, 'pendingBoxes']);
            Route::post('/boxes/requests/{id}/accept-all', [DeliveryMobileController::class, 'acceptAllBoxes']);
            Route::post('/boxes/requests/{id}/hand-all', [DeliveryMobileController::class, 'handAllBoxes']);
            Route::post('/boxes/{id}/accept-warehouse', [DeliveryMobileController::class, 'acceptWarehouse']);
            Route::post('/boxes/{id}/hand-to-kitchen', [DeliveryMobileController::class, 'handToKitchen']);
            Route::post('/boxes/{id}/accept-kitchen-return', [DeliveryMobileController::class, 'acceptKitchenReturn']);
            Route::post('/boxes/{id}/hand-to-ops', [DeliveryMobileController::class, 'handToOps']);
            Route::post('/boxes/{id}/collect-empty', [DeliveryMobileController::class, 'collectEmpty']);
        });

        Route::middleware('permission:'.DeliveryPermissions::CASH)->group(function () {
            Route::get('/orders/delivered', [DeliveryMobileController::class, 'deliveredOrders']);
            Route::post('/orders/{id}/collect-cash', [DeliveryMobileController::class, 'collectCash']);
            Route::get('/cash-handovers', [DeliveryMobileController::class, 'cashHandovers']);
            Route::post('/cash-handovers', [DeliveryMobileController::class, 'createCashHandover']);
        });

        Route::middleware('permission:'.DeliveryPermissions::ACCOUNT)->group(function () {
            Route::get('/account', [DeliveryMobileController::class, 'account']);
            Route::post('/account/withdraw', [DeliveryMobileController::class, 'requestWithdrawal']);
        });
    });
});
