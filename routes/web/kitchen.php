<?php

use App\Livewire\Kitchen\Account;
use App\Livewire\Kitchen\ActiveOrders;
use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Kitchen\CashHandovers;
use App\Livewire\Kitchen\Dashboard;
use App\Livewire\Kitchen\IncomingBoxes;
use App\Livewire\Kitchen\MenuDetails;
use App\Livewire\Kitchen\MiddoOrderGroups;
use App\Livewire\Kitchen\OrdersLastThreeMonths;
use App\Livewire\Kitchen\OrdersThisMonth;
use App\Livewire\Kitchen\PrepShoppingList;
use App\Livewire\Kitchen\RecipeShow;
use App\Livewire\Kitchen\TodayMenus;
use App\Livewire\Shared\StaffAlertsPage;
use Illuminate\Support\Facades\Route;

// routes/web/kitchen.php
Route::middleware(['auth', 'role:kitchen'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('kitchen.dashboard');
    Route::get('/alerts', StaffAlertsPage::class)->name('kitchen.alerts');

    Route::get('/orders/this-month', OrdersThisMonth::class)->name('kitchen.orders.this-month');
    Route::get('/orders/last-three-months', OrdersLastThreeMonths::class)->name('kitchen.orders.last-three-months');
    Route::get('/orders/active', ActiveOrders::class)->name('kitchen.orders.active');
    Route::get('/menus/today', TodayMenus::class)->name('kitchen.menus.today');
    Route::get('/prep/shopping-list', PrepShoppingList::class)->name('kitchen.prep.shopping-list');

    Route::get('/order-groups/middo', MiddoOrderGroups::class)->name('kitchen.order-groups.middo');

    Route::get('/middo-boxes/at-kitchen', BoxesAtKitchen::class)->name('kitchen.middo-boxes.at-kitchen');
    Route::get('/middo-boxes/incoming', IncomingBoxes::class)->name('kitchen.middo-boxes.incoming');
    Route::get('/cash-handovers', CashHandovers::class)->name('kitchen.cash-handovers');
    Route::get('/account', Account::class)->name('kitchen.account');

    Route::get('/menus/{menuItem}', MenuDetails::class)->name('kitchen.menus.show');
    Route::get('/menus/{menuItem}/meal-items/{mealItem}/recipe', RecipeShow::class)
        ->name('kitchen.menus.meal-items.recipe');
});
