<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Delivery\Dashboard;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Delivery\DeliveredOrders;

// routes/web/deliveryman.php
Route::middleware(['auth', 'role:delivery'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('delivery.dashboard');
    Route::get('/kitchen-dispatches', KitchenDispatches::class)->name('delivery.kitchen-dispatches');
    Route::get('/middo-boxes/pending-run', PendingBoxRuns::class)->name('delivery.middo-boxes.pending-run');
    Route::get('/orders/delivered', DeliveredOrders::class)->name('delivery.orders.delivered');
});
