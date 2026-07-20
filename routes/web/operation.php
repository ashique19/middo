<?php

use App\Http\Controllers\Operation\MiddoBoxPrintController;
use App\Livewire\Operation\ActiveOrders;
use App\Livewire\Operation\Dashboard;
use App\Livewire\Operation\KitchenAllOrders;
use App\Livewire\Operation\Kitchens;
use App\Livewire\Operation\MiddoBoxes;
use App\Livewire\Operation\OrderHistory;
use App\Livewire\Operation\SearchOrder;
use App\Livewire\Shared\CorporateShow;
use App\Livewire\Shared\CorporateTable;
use App\Livewire\Shared\MiddoCashLedgerPage;
use App\Livewire\Shared\PackageBuilder;
use App\Livewire\Shared\PackageDemand;
use App\Livewire\Shared\PackageInsights;
use App\Livewire\Shared\SubscriptionShow;
use Illuminate\Support\Facades\Route;

// routes/web/operation.php
Route::middleware(['auth', 'role:operation'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('operation.dashboard');
    Route::get('/corporates', CorporateTable::class)->name('operation.corporates.index');
    Route::get('/corporates/{corporate}', CorporateShow::class)->name('operation.corporates.show');
    Route::get('/kitchens', Kitchens::class)->name('operation.kitchens.index');
    Route::get('/kitchens/{kitchen}/orders', KitchenAllOrders::class)->name('operation.kitchens.orders');

    Route::get('/menu', function () {
        return view('operation.menu.page');
    })->name('operation.menu.index');

    Route::get('/meal-items', function () {
        return view('operation.meal-items.page');
    })->name('operation.meal-items.index');

    Route::get('/packages', fn () => view('operation.packages.page'))->name('operation.packages.index');
    Route::get('/packages/create', PackageBuilder::class)->name('operation.packages.create');
    Route::get('/packages/{package}/edit', PackageBuilder::class)->name('operation.packages.edit');
    Route::get('/subscriptions', fn () => view('operation.subscriptions.page'))->name('operation.subscriptions.index');
    Route::get('/subscriptions/{subscription}', SubscriptionShow::class)->name('operation.subscriptions.show');
    Route::get('/package-demand', PackageDemand::class)->name('operation.packages.demand');
    Route::get('/package-insights', PackageInsights::class)->name('operation.packages.insights');

    Route::get('/orders/active', ActiveOrders::class)->name('operation.orders.active');
    Route::get('/orders/history', OrderHistory::class)->name('operation.orders.history');
    Route::get('/orders/search', SearchOrder::class)->name('operation.orders.search');

    Route::get('/middo-boxes', MiddoBoxes::class)->name('operation.middo-boxes.index');
    Route::get('/middo-boxes/{middoBox}/print', MiddoBoxPrintController::class)->name('operation.middo-boxes.print');
    Route::get('/middo-cash', MiddoCashLedgerPage::class)->name('operation.middo-cash');
});
