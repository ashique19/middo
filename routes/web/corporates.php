<?php

use App\Http\Controllers\Corporates\DashboardController;
use Illuminate\Support\Facades\Route;


// Route::middleware(['role:admin', 'permission:manage-kitchen'])->group(function () {
//     Route::get('/kitchen/settings', [KitchenController::class, 'settings']);
// });

Route::middleware(['auth', 'role:corporate'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('corporates.dashboard');
});