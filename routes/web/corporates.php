<?php

use App\Livewire\Corporate\ChangePassword as CorporateChangePassword;
use App\Livewire\Corporate\Dashboard as CorporateDashboard;
use App\Livewire\Corporate\OrderHistory;
use App\Livewire\Corporate\Profile as CorporateProfile;
use App\Livewire\Corporate\ScheduledOrders;
use Illuminate\Support\Facades\Route;


// Route::middleware(['role:admin', 'permission:manage-kitchen'])->group(function () {
//     Route::get('/kitchen/settings', [KitchenController::class, 'settings']);
// });

Route::middleware(['auth', 'role:corporate'])->group(function () {



    Route::get('/dashboard', CorporateDashboard::class)->name('corporates.dashboard');
    Route::get('/orders/scheduled', ScheduledOrders::class)->name('corporates.orders.scheduled');
    Route::get('/orders/history', OrderHistory::class)->name('corporates.orders.history');

    Route::get('/profile', CorporateProfile::class)->name('corporates.profile');

    Route::get('/change-password', CorporateChangePassword::class)->name('corporates.change-password');

});
