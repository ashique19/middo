<?php

use App\Http\Controllers\Api\Operation\OperationMobileController;
use App\Support\OperationPermissions;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operation Mobile API (Flutter / Android field-pulse app)
|--------------------------------------------------------------------------
| Contract: docs/operation-mobile-api-contract.md
| Plan: docs/operation-mobile-plan.json
*/

Route::prefix('operation')->group(function () {
    Route::post('/login', [OperationMobileController::class, 'login']);

    Route::middleware(['auth:sanctum', 'role:operation'])->group(function () {
        Route::post('/logout', [OperationMobileController::class, 'logout']);
        Route::get('/me', [OperationMobileController::class, 'me']);
        Route::post('/change-password', [OperationMobileController::class, 'changePassword']);
        Route::post('/device-tokens', [OperationMobileController::class, 'registerDeviceToken']);
        Route::delete('/device-tokens', [OperationMobileController::class, 'unregisterDeviceToken']);

        Route::middleware('permission:'.OperationPermissions::DASHBOARD)
            ->get('/dashboard', [OperationMobileController::class, 'dashboard']);

        Route::middleware('permission:'.OperationPermissions::ALERTS)->group(function () {
            Route::get('/alerts', [OperationMobileController::class, 'alerts']);
            Route::patch('/alerts/{id}/read', [OperationMobileController::class, 'markAlertRead']);
            Route::post('/alerts/read-all', [OperationMobileController::class, 'markAllAlertsRead']);
        });
    });
});
