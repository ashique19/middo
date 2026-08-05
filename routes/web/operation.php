<?php

use App\Http\Controllers\Operation\MiddoBoxPrintController;
use App\Livewire\Operation\ActiveOrders;
use App\Livewire\Operation\CashHandovers;
use App\Livewire\Operation\Complaints;
use App\Livewire\Operation\ComplaintShow;
use App\Livewire\Operation\CoverageBoard;
use App\Livewire\Operation\CustomRuns;
use App\Livewire\Operation\KitchenAllOrders;
use App\Livewire\Operation\Kitchens;
use App\Livewire\Operation\MiddoBoxes;
use App\Livewire\Operation\MiddoBoxShow;
use App\Livewire\Operation\OpsDayBoard;
use App\Livewire\Operation\OrderHistory;
use App\Livewire\Operation\RidersBoard;
use App\Livewire\Operation\SearchOrder;
use App\Livewire\Operation\SlaBoard;
use App\Livewire\Shared\AccountsHub;
use App\Livewire\Shared\AreasAdmin;
use App\Livewire\Shared\CashPositionsBoard;
use App\Livewire\Shared\CodDueReconPage;
use App\Livewire\Shared\CorporateShow;
use App\Livewire\Shared\CorporateTable;
use App\Livewire\Shared\KitchenMoneyApprovals;
use App\Livewire\Shared\MealItemShow;
use App\Livewire\Shared\MenuShow;
use App\Livewire\Shared\MiddoCashLedgerPage;
use App\Livewire\Shared\OperatingCostsPage;
use App\Livewire\Shared\OrderShow;
use App\Livewire\Shared\PackageBuilder;
use App\Livewire\Shared\PackageDemand;
use App\Livewire\Shared\PackageInsights;
use App\Livewire\Shared\RecipeShow;
use App\Livewire\Shared\RiderMoneyApprovals;
use App\Livewire\Shared\StaffAlertsPage;
use App\Livewire\Shared\StaffDashboard;
use App\Livewire\Shared\StaffProfileShow;
use App\Livewire\Shared\SubscriptionShow;
use Illuminate\Support\Facades\Route;

// routes/web/operation.php
Route::middleware(['auth', 'role:operation'])->group(function () {
    Route::get('/dashboard', StaffDashboard::class)->name('operation.dashboard');
    Route::get('/alerts', StaffAlertsPage::class)->name('operation.alerts.index');
    Route::get('/corporates', CorporateTable::class)->name('operation.corporates.index');
    Route::get('/corporates/{corporate}', CorporateShow::class)->name('operation.corporates.show');
    Route::get('/kitchens', Kitchens::class)->name('operation.kitchens.index');
    Route::get('/kitchens/{kitchen}/orders', KitchenAllOrders::class)->name('operation.kitchens.orders');
    Route::get('/kitchens/{kitchen}', StaffProfileShow::class)->name('operation.kitchens.show');
    Route::get('/deliveries/{delivery}', StaffProfileShow::class)->name('operation.deliveries.show');

    Route::get('/menu', function () {
        return view('operation.menu.page');
    })->name('operation.menu.index');
    Route::get('/menu/{menuItem}', MenuShow::class)->name('operation.menu.show');

    Route::get('/meal-items', function () {
        return view('operation.meal-items.page');
    })->name('operation.meal-items.index');
    Route::get('/meal-items/{mealItem}', MealItemShow::class)->name('operation.meal-items.show');
    Route::get('/recipes/{recipe}', RecipeShow::class)->name('operation.recipes.show');

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
    Route::get('/orders/{order}', OrderShow::class)->name('operation.orders.show');

    Route::get('/middo-boxes', MiddoBoxes::class)->name('operation.middo-boxes.index');
    Route::get('/middo-boxes/{middoBox}', MiddoBoxShow::class)->name('operation.middo-boxes.show');
    Route::get('/middo-boxes/{middoBox}/print', MiddoBoxPrintController::class)->name('operation.middo-boxes.print');
    Route::get('/middo-cash', MiddoCashLedgerPage::class)->name('operation.middo-cash');
    Route::get('/cash-positions', CashPositionsBoard::class)->name('operation.cash-positions');
    Route::get('/cash-handovers', CashHandovers::class)->name('operation.cash-handovers');
    Route::get('/sla', SlaBoard::class)->name('operation.sla.index');
    Route::get('/riders', RidersBoard::class)->name('operation.riders.index');
    Route::get('/areas', AreasAdmin::class)->name('operation.areas.index');
    Route::get('/coverage', CoverageBoard::class)->name('operation.coverage.index');
    Route::get('/ops-day', OpsDayBoard::class)->name('operation.ops-day.index');
    Route::get('/complaints', Complaints::class)->name('operation.complaints.index');
    Route::get('/complaints/{complaint}', ComplaintShow::class)->name('operation.complaints.show');
    Route::get('/accounts', AccountsHub::class)->name('operation.accounts.index');
    Route::get('/cod-recon', CodDueReconPage::class)->name('operation.cod-recon.index');
    Route::get('/operating-costs', OperatingCostsPage::class)->name('operation.operating-costs.index');
    Route::get('/kitchen-money', KitchenMoneyApprovals::class)->name('operation.kitchen-money.index');
    Route::get('/rider-money', RiderMoneyApprovals::class)->name('operation.rider-money.index');
    Route::get('/custom-runs', CustomRuns::class)->name('operation.custom-runs.index');
});
