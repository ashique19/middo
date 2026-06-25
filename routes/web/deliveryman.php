<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Delivery\DashboardController;

// routes/web/delivery.php
Route::middleware(['auth', 'role:delivery'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('delivery.dashboard');
});