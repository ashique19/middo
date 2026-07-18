<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NavRoleController;
use App\Livewire\Operation\ActiveOrders;
use App\Livewire\Operation\OrderHistory;
use App\Livewire\Operation\SearchOrder;
use App\Livewire\Operation\Kitchens;
use App\Livewire\Operation\KitchenAllOrders;
use App\Livewire\Admin\KitchenOnboarding;
use App\Livewire\Shared\MiddoCashLedgerPage;

// routes/web/admin.php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('/kitchens/active', Kitchens::class)->name('admin.kitchens.active');
    Route::get('/kitchens/onboarding', KitchenOnboarding::class)->name('admin.kitchens.onboarding');
    Route::get('/kitchens/{kitchen}/orders', KitchenAllOrders::class)->name('admin.kitchens.orders');
    Route::redirect('/kitchens', '/admin/kitchens/active')->name('admin.kitchens.index');

    Route::get('/menu', fn () => view('admin.menu.page'))->name('admin.menu.index');
    Route::get('/meal-items', fn () => view('admin.meal-items.page'))->name('admin.meal-items.index');
    Route::get('/packages', fn () => view('admin.packages.page'))->name('admin.packages.index');
    Route::get('/packages/create', \App\Livewire\Shared\PackageBuilder::class)->name('admin.packages.create');
    Route::get('/packages/{package}/edit', \App\Livewire\Shared\PackageBuilder::class)->name('admin.packages.edit');

    Route::get('/orders/active', ActiveOrders::class)->name('admin.orders.active');
    Route::get('/orders/history', OrderHistory::class)->name('admin.orders.history');
    Route::get('/orders/search', SearchOrder::class)->name('admin.orders.search');

    Route::get('/navs-roles', [NavRoleController::class, 'index'])->name('admin.navrole.index');
    Route::get('/middo-cash', MiddoCashLedgerPage::class)->name('admin.middo-cash');

    // User Management (one page per role)
    Route::get('/users/admin', fn () => view('admin.users.page', ['role' => 'admin']))->name('admin.users.admin');
    Route::get('/users/operation', fn () => view('admin.users.page', ['role' => 'operation']))->name('admin.users.operation');
    Route::get('/users/kitchen', fn () => view('admin.users.page', ['role' => 'kitchen']))->name('admin.users.kitchen');
    Route::get('/users/corporate', fn () => view('admin.users.page', ['role' => 'corporate']))->name('admin.users.corporate');
    Route::get('/users/delivery', fn () => view('admin.users.page', ['role' => 'delivery']))->name('admin.users.delivery');
    Route::get('/users/{role?}', fn ($role = null) => view('admin.users.page', ['role' => $role]))->name('admin.users.index');
});
