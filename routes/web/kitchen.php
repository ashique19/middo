<?php

use App\Livewire\Kitchen\Account;
use App\Livewire\Kitchen\ActiveOrders;
use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Kitchen\CashHandovers;
use App\Livewire\Kitchen\Complaints;
use App\Livewire\Kitchen\ComplaintShow;
use App\Livewire\Kitchen\Dashboard;
use App\Livewire\Kitchen\IncomingBoxes;
use App\Livewire\Kitchen\MenuDetails;
use App\Livewire\Kitchen\MiddoOrderGroups;
use App\Livewire\Kitchen\OrdersLastThreeMonths;
use App\Livewire\Kitchen\OrdersThisMonth;
use App\Livewire\Kitchen\PrepShoppingList;
use App\Livewire\Kitchen\Profile;
use App\Livewire\Kitchen\RecipeShow;
use App\Livewire\Kitchen\TodayMenus;
use App\Livewire\Shared\StaffAlertsPage;
use App\Support\KitchenPermissions;
use Illuminate\Support\Facades\Route;

// routes/web/kitchen.php
Route::middleware(['auth', 'role:kitchen'])->group(function () {
    Route::middleware('permission:'.KitchenPermissions::DASHBOARD)
        ->get('/dashboard', Dashboard::class)->name('kitchen.dashboard');

    Route::middleware('permission:'.KitchenPermissions::ALERTS)
        ->get('/alerts', StaffAlertsPage::class)->name('kitchen.alerts');

    Route::middleware('permission:'.KitchenPermissions::ORDERS)->group(function () {
        Route::get('/orders/this-month', OrdersThisMonth::class)->name('kitchen.orders.this-month');
        Route::get('/orders/last-three-months', OrdersLastThreeMonths::class)->name('kitchen.orders.last-three-months');
        Route::get('/orders/active', ActiveOrders::class)->name('kitchen.orders.active');
    });

    Route::middleware('permission:'.KitchenPermissions::MENUS)->group(function () {
        Route::get('/menus/today', TodayMenus::class)->name('kitchen.menus.today');
        Route::get('/menus/{menuItem}', MenuDetails::class)->name('kitchen.menus.show');
        Route::get('/menus/{menuItem}/meal-items/{mealItem}/recipe', RecipeShow::class)
            ->name('kitchen.menus.meal-items.recipe');
    });

    Route::middleware('permission:'.KitchenPermissions::PREP)
        ->get('/prep/shopping-list', PrepShoppingList::class)->name('kitchen.prep.shopping-list');

    Route::middleware('permission:'.KitchenPermissions::ORDER_GROUPS)
        ->get('/order-groups/middo', MiddoOrderGroups::class)->name('kitchen.order-groups.middo');

    Route::middleware('permission:'.KitchenPermissions::BOXES)->group(function () {
        Route::get('/middo-boxes/at-kitchen', BoxesAtKitchen::class)->name('kitchen.middo-boxes.at-kitchen');
        Route::get('/middo-boxes/incoming', IncomingBoxes::class)->name('kitchen.middo-boxes.incoming');
    });

    Route::middleware('permission:'.KitchenPermissions::CASH_HANDOVERS)
        ->get('/cash-handovers', CashHandovers::class)->name('kitchen.cash-handovers');

    Route::middleware('permission:'.KitchenPermissions::ACCOUNT)
        ->get('/account', Account::class)->name('kitchen.account');

    Route::middleware('permission:'.KitchenPermissions::COMPLAINTS)->group(function () {
        Route::get('/complaints', Complaints::class)->name('kitchen.complaints');
        Route::get('/complaints/{complaint}', ComplaintShow::class)->name('kitchen.complaints.show');
    });

    Route::middleware('permission:'.KitchenPermissions::PROFILE)
        ->get('/profile', Profile::class)->name('kitchen.profile');
});
