<?php

use App\Livewire\Corporate\ChangePassword as CorporateChangePassword;
use App\Livewire\Corporate\Dashboard as CorporateDashboard;
use App\Livewire\Corporate\OrderHistory;
use App\Livewire\Corporate\Packages as CorporatePackages;
use App\Livewire\Corporate\PackageShow as CorporatePackageShow;
use App\Livewire\Corporate\Profile as CorporateProfile;
use App\Livewire\Corporate\ScheduledOrders;
use App\Livewire\Corporate\Wallet as CorporateWallet;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:corporate'])->group(function () {
    Route::get('/dashboard', CorporateDashboard::class)->name('corporates.dashboard');
    Route::get('/packages', CorporatePackages::class)->name('corporates.packages.index');
    Route::get('/packages/{subscription}', CorporatePackageShow::class)->name('corporates.packages.show');
    Route::get('/orders/scheduled', ScheduledOrders::class)->name('corporates.orders.scheduled');
    Route::get('/orders/history', OrderHistory::class)->name('corporates.orders.history');
    Route::get('/wallet', CorporateWallet::class)->name('corporates.wallet');
    Route::get('/profile', CorporateProfile::class)->name('corporates.profile');
    Route::get('/change-password', CorporateChangePassword::class)->name('corporates.change-password');
});
