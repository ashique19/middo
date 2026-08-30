<?php

use App\Http\Controllers\Api\Kitchen\KitchenMobileController;
use App\Support\KitchenPermissions;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kitchen Mobile API (Flutter / Android app)
|--------------------------------------------------------------------------
| Contract: docs/kitchen-mobile-api-contract.md
*/

Route::prefix('kitchen')->group(function () {
    Route::post('/login', [KitchenMobileController::class, 'login']);

    Route::middleware(['auth:sanctum', 'role:kitchen'])->group(function () {
        Route::post('/logout', [KitchenMobileController::class, 'logout']);
        Route::get('/me', [KitchenMobileController::class, 'me']);
        Route::post('/change-password', [KitchenMobileController::class, 'changePassword']);
        Route::post('/device-tokens', [KitchenMobileController::class, 'registerDeviceToken']);
        Route::delete('/device-tokens', [KitchenMobileController::class, 'unregisterDeviceToken']);

        Route::middleware('permission:'.KitchenPermissions::PROFILE)
            ->patch('/profile', [KitchenMobileController::class, 'updateProfile']);

        Route::middleware('permission:'.KitchenPermissions::DASHBOARD)
            ->get('/dashboard', [KitchenMobileController::class, 'dashboard']);

        Route::middleware('permission:'.KitchenPermissions::ALERTS)->group(function () {
            Route::get('/alerts', [KitchenMobileController::class, 'alerts']);
            Route::patch('/alerts/{id}/read', [KitchenMobileController::class, 'markAlertRead']);
            Route::post('/alerts/read-all', [KitchenMobileController::class, 'markAllAlertsRead']);
        });

        Route::middleware('permission:'.KitchenPermissions::ORDER_GROUPS)->group(function () {
            Route::get('/order-groups', [KitchenMobileController::class, 'orderGroups']);
            Route::post('/order-groups/{id}/accept', [KitchenMobileController::class, 'acceptOrderGroup']);
            Route::post('/order-groups/{id}/decline', [KitchenMobileController::class, 'declineOrderGroup']);
        });

        Route::middleware('permission:'.KitchenPermissions::ORDERS)->group(function () {
            Route::get('/orders/active', [KitchenMobileController::class, 'activeOrders']);
            Route::get('/orders/{id}', [KitchenMobileController::class, 'showOrder']);
            Route::post('/orders/{id}/ready', [KitchenMobileController::class, 'markOrderReady']);
            Route::post('/order-groups/{id}/ready', [KitchenMobileController::class, 'markGroupReady']);
            Route::post('/order-groups/{id}/release', [KitchenMobileController::class, 'releaseOrderGroup']);
            Route::post('/order-groups/{id}/shortage', [KitchenMobileController::class, 'reportShortage']);
        });

        Route::middleware('permission:'.KitchenPermissions::MENUS)->group(function () {
            Route::get('/menus/today', [KitchenMobileController::class, 'menusToday']);
            Route::get('/menus/{id}', [KitchenMobileController::class, 'showMenu']);
        });

        Route::middleware('permission:'.KitchenPermissions::BOXES)
            ->get('/boxes/at-kitchen', [KitchenMobileController::class, 'boxesAtKitchen']);
    });
});
