<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KitchenController;
use App\Http\Controllers\Admin\NavRoleController;

// routes/web/admin.php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/kitchens', [KitchenController::class, 'index'])->name('admin.kitchens.index');

    // NavRoleController routes
    Route::get('/navs-roles', [NavRoleController::class, 'index'])->name('admin.navrole.index');
    
    // User Management
    // Define these explicitly so they are hard-coded in your route cache
    Route::get('/users/admin', fn() => view('admin.users.page', ['role' => 'admin']))->name('admin.users.admin');
    Route::get('/users/operation', fn() => view('admin.users.page', ['role' => 'operation']))->name('admin.users.operation');
    Route::get('/users/kitchen', fn() => view('admin.users.page', ['role' => 'kitchen']))->name('admin.users.kitchen');
    Route::get('/users/corporate', fn() => view('admin.users.page', ['role' => 'corporate']))->name('admin.users.corporate');
    Route::get('/users/delivery', fn() => view('admin.users.page', ['role' => 'delivery']))->name('admin.users.delivery');

    // Catch-all for others
    Route::get('/users/{role?}', fn($role = null) => view('admin.users.page', ['role' => $role]))->name('admin.users.index');



});