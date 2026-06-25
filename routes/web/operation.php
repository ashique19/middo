<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Operation\DashboardController;
use App\Http\Controllers\Operation\KitchensController;

// routes/web/operation.php
Route::middleware(['auth', 'role:operation'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('operation.dashboard');
    Route::get('/kitchens', [KitchensController::class, 'index'])->name('operation.kitchens.index');

    Route::get('/menu', function () {
        return view('operation.menu.page');
    })->name('operation.menu.index');
});