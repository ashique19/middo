<?php

use App\Http\Controllers\Api\Operation\OperationMobileController;
use App\Http\Controllers\Api\Operation\OperationMobileFieldController;
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

        Route::middleware('permission:'.OperationPermissions::DASHBOARD)->group(function () {
            Route::get('/dashboard', [OperationMobileController::class, 'dashboard']);
            Route::get('/ops-day', [OperationMobileFieldController::class, 'opsDay']);
        });

        Route::middleware('permission:'.OperationPermissions::ALERTS)->group(function () {
            Route::get('/alerts', [OperationMobileController::class, 'alerts']);
            Route::patch('/alerts/{id}/read', [OperationMobileController::class, 'markAlertRead']);
            Route::post('/alerts/read-all', [OperationMobileController::class, 'markAllAlertsRead']);
        });

        Route::middleware('permission:'.OperationPermissions::BOXES)->group(function () {
            Route::get('/boxes', [OperationMobileFieldController::class, 'boxes']);
            Route::get('/boxes/lookup', [OperationMobileFieldController::class, 'lookupBox']);
            Route::get('/boxes/requests', [OperationMobileFieldController::class, 'boxRequests']);
            Route::post('/boxes/requests/{id}/assign', [OperationMobileFieldController::class, 'assignBoxRequest']);
            Route::post('/boxes/{id}/reassign', [OperationMobileFieldController::class, 'reassignBox']);
            Route::post('/boxes/{id}/ack-return', [OperationMobileFieldController::class, 'ackBoxReturn']);
        });

        Route::middleware('permission:'.OperationPermissions::RIDERS)->group(function () {
            Route::get('/riders/board', [OperationMobileFieldController::class, 'ridersBoard']);
            Route::post('/orders/{id}/assign-rider', [OperationMobileFieldController::class, 'assignLunchRider']);
            Route::post('/orders/{id}/reassign-rider', [OperationMobileFieldController::class, 'reassignLunchRider']);
            Route::post('/custom-runs', [OperationMobileFieldController::class, 'createCustomRun']);
            Route::post('/custom-runs/{id}/cancel', [OperationMobileFieldController::class, 'cancelCustomRun']);
        });

        Route::middleware('permission:'.OperationPermissions::CASH)->group(function () {
            Route::get('/cash-handovers', [OperationMobileFieldController::class, 'cashHandovers']);
            Route::post('/cash-handovers/{id}/accept', [OperationMobileFieldController::class, 'acceptCashHandover']);
            Route::post('/cash-handovers/{id}/reject', [OperationMobileFieldController::class, 'rejectCashHandover']);
        });

        Route::middleware('permission:'.OperationPermissions::SLA)->group(function () {
            Route::get('/sla', [OperationMobileFieldController::class, 'slaBoard']);
            Route::post('/order-groups/{id}/assign-kitchen', [OperationMobileFieldController::class, 'assignKitchen']);
            Route::post('/order-groups/bulk-assign-kitchen', [OperationMobileFieldController::class, 'bulkAssignKitchen']);
        });

        Route::middleware('permission:'.OperationPermissions::COMPLAINTS)->group(function () {
            Route::get('/complaints', [OperationMobileFieldController::class, 'complaints']);
            Route::get('/complaints/{id}', [OperationMobileFieldController::class, 'showComplaint']);
            Route::post('/complaints/{id}/reply', [OperationMobileFieldController::class, 'replyComplaint']);
            Route::post('/complaints/{id}/complete', [OperationMobileFieldController::class, 'completeComplaint']);
        });

        Route::middleware('permission:'.OperationPermissions::ORDERS)->group(function () {
            Route::get('/orders/search', [OperationMobileFieldController::class, 'searchOrders']);
            Route::get('/orders/{id}', [OperationMobileFieldController::class, 'showOrder']);
            Route::post('/orders/{id}/force-cancel', [OperationMobileFieldController::class, 'forceCancelOrder']);
            Route::post('/orders/{id}/release-rider', [OperationMobileFieldController::class, 'releaseRider']);
        });
    });
});
