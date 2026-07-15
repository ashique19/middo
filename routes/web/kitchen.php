<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Kitchen\Dashboard;
use App\Livewire\Kitchen\ActiveOrders;
use App\Livewire\Kitchen\MiddoOrderGroups;
use App\Livewire\Kitchen\OrdersThisMonth;
use App\Livewire\Kitchen\OrdersLastThreeMonths;
use App\Livewire\Kitchen\MenuDetails;
use App\Livewire\Kitchen\RecipeShow;

// routes/web/kitchen.php
Route::middleware(['auth', 'role:kitchen'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('kitchen.dashboard');

    Route::get('/orders/this-month', OrdersThisMonth::class)->name('kitchen.orders.this-month');
    Route::get('/orders/last-three-months', OrdersLastThreeMonths::class)->name('kitchen.orders.last-three-months');
    Route::get('/orders/active', ActiveOrders::class)->name('kitchen.orders.active');

    Route::get('/order-groups/middo', MiddoOrderGroups::class)->name('kitchen.order-groups.middo');

    Route::get('/menus/{menuItem}', MenuDetails::class)->name('kitchen.menus.show');
    Route::get('/menus/{menuItem}/meal-items/{mealItem}/recipe', RecipeShow::class)
        ->name('kitchen.menus.meal-items.recipe');
});
