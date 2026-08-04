<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Delivery\Dashboard;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Delivery\DeliveredOrders;
use App\Livewire\Delivery\CashHandovers;
use App\Livewire\Delivery\Account;
use App\Livewire\Delivery\CustomRuns;
use App\Livewire\Shared\StaffAlertsPage;

// routes/web/deliveryman.php
Route::middleware(['auth', 'role:delivery'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('delivery.dashboard');
    Route::get('/kitchen-dispatches', KitchenDispatches::class)->name('delivery.kitchen-dispatches');
    Route::get('/middo-boxes/pending-run', PendingBoxRuns::class)->name('delivery.middo-boxes.pending-run');
    Route::get('/orders/delivered', DeliveredOrders::class)->name('delivery.orders.delivered');
    Route::get('/orders/{order}', \App\Livewire\Shared\OrderShow::class)->name('delivery.orders.show');
    Route::get('/cash-handovers', CashHandovers::class)->name('delivery.cash-handovers');
    Route::get('/custom-runs', CustomRuns::class)->name('delivery.custom-runs');
    Route::get('/account', Account::class)->name('delivery.account');
    Route::get('/alerts', StaffAlertsPage::class)->name('delivery.alerts');
});
