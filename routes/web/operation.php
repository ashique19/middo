<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Operation\Dashboard;
use App\Livewire\Operation\ActiveOrders;
use App\Livewire\Operation\OrderHistory;
use App\Livewire\Operation\SearchOrder;
use App\Livewire\Operation\Kitchens;
use App\Livewire\Operation\KitchenAllOrders;
use App\Livewire\Operation\MiddoBoxes;
use App\Http\Controllers\Operation\MiddoBoxPrintController;

// routes/web/operation.php
Route::middleware(['auth', 'role:operation'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('operation.dashboard');
    Route::get('/kitchens', Kitchens::class)->name('operation.kitchens.index');
    Route::get('/kitchens/{kitchen}/orders', KitchenAllOrders::class)->name('operation.kitchens.orders');

    Route::get('/menu', function () {
        return view('operation.menu.page');
    })->name('operation.menu.index');

    Route::get('/meal-items', function () {
        return view('operation.meal-items.page');
    })->name('operation.meal-items.index');

    Route::get('/orders/active', ActiveOrders::class)->name('operation.orders.active');
    Route::get('/orders/history', OrderHistory::class)->name('operation.orders.history');
    Route::get('/orders/search', SearchOrder::class)->name('operation.orders.search');

    Route::get('/middo-boxes', MiddoBoxes::class)->name('operation.middo-boxes.index');
    Route::get('/middo-boxes/{middoBox}/print', MiddoBoxPrintController::class)->name('operation.middo-boxes.print');
});
