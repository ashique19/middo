<?php

use App\Livewire\Corporate\Dashboard as CorporateDashboard;
use Illuminate\Support\Facades\Route;


// Route::middleware(['role:admin', 'permission:manage-kitchen'])->group(function () {
//     Route::get('/kitchen/settings', [KitchenController::class, 'settings']);
// });

Route::middleware(['auth', 'role:corporate'])->group(function () {



    Route::get('/dashboard', CorporateDashboard::class)->name('corporates.dashboard');

});