<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Kitchen\DashboardController;

// routes/web/kitchen.php
Route::middleware(['auth', 'role:kitchen'])->group(function() {
    Route::get('/kitchen/dashboard', [DashboardController::class, 'index'])->name('kitchen.dashboard');
});