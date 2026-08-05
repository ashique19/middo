<?php

use App\Http\Controllers\Admin\NavRoleController;
use App\Livewire\Admin\BankAccountsPage;
use App\Livewire\Admin\KitchenOnboarding;
use App\Livewire\Admin\SettingsPage;
use App\Livewire\Admin\UserShow;
use App\Livewire\Operation\ActiveOrders;
use App\Livewire\Operation\CashHandovers;
use App\Livewire\Operation\Complaints;
use App\Livewire\Operation\ComplaintShow;
use App\Livewire\Operation\CoverageBoard;
use App\Livewire\Operation\CustomRuns;
use App\Livewire\Operation\KitchenAllOrders;
use App\Livewire\Operation\Kitchens;
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
use App\Livewire\Shared\MiddoBankLedgerPage;
use App\Livewire\Shared\MiddoCashLedgerPage;
use App\Livewire\Shared\OperatingCostsPage;
use App\Livewire\Shared\OrderShow;
use App\Livewire\Shared\PackageBuilder;
use App\Livewire\Shared\PackageDemand;
use App\Livewire\Shared\PackageInsights;
use App\Livewire\Shared\PeriodPnlPage;
use App\Livewire\Shared\RecipeShow;
use App\Livewire\Shared\RiderMoneyApprovals;
use App\Livewire\Shared\StaffAlertsPage;
use App\Livewire\Shared\StaffDashboard;
use App\Livewire\Shared\StaffProfileShow;
use App\Livewire\Shared\SubscriptionShow;
use Illuminate\Support\Facades\Route;

// routes/web/admin.php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', StaffDashboard::class)->name('admin.dashboard');
    Route::get('/alerts', StaffAlertsPage::class)->name('admin.alerts.index');

    Route::get('/kitchens/active', Kitchens::class)->name('admin.kitchens.active');
    Route::get('/kitchens/onboarding', KitchenOnboarding::class)->name('admin.kitchens.onboarding');
    Route::get('/kitchens/{kitchen}/orders', KitchenAllOrders::class)->name('admin.kitchens.orders');
    Route::get('/kitchens/{kitchen}', StaffProfileShow::class)->name('admin.kitchens.show');
    Route::get('/deliveries/{delivery}', StaffProfileShow::class)->name('admin.deliveries.show');
    Route::redirect('/kitchens', '/admin/kitchens/active')->name('admin.kitchens.index');

    Route::get('/menu', fn () => view('admin.menu.page'))->name('admin.menu.index');
    Route::get('/menu/{menuItem}', MenuShow::class)->name('admin.menu.show');
    Route::get('/meal-items', fn () => view('admin.meal-items.page'))->name('admin.meal-items.index');
    Route::get('/meal-items/{mealItem}', MealItemShow::class)->name('admin.meal-items.show');
    Route::get('/recipes/{recipe}', RecipeShow::class)->name('admin.recipes.show');
    Route::get('/packages', fn () => view('admin.packages.page'))->name('admin.packages.index');
    Route::get('/packages/create', PackageBuilder::class)->name('admin.packages.create');
    Route::get('/packages/{package}/edit', PackageBuilder::class)->name('admin.packages.edit');
    Route::get('/coupons', fn () => view('admin.coupons.page'))->name('admin.coupons.index');
    Route::get('/charges', fn () => view('admin.charges.page'))->name('admin.charges.index');
    Route::get('/subscriptions', fn () => view('admin.subscriptions.page'))->name('admin.subscriptions.index');
    Route::get('/subscriptions/{subscription}', SubscriptionShow::class)->name('admin.subscriptions.show');
    Route::get('/package-demand', PackageDemand::class)->name('admin.packages.demand');
    Route::get('/package-insights', PackageInsights::class)->name('admin.packages.insights');

    Route::get('/orders/active', ActiveOrders::class)->name('admin.orders.active');
    Route::get('/orders/history', OrderHistory::class)->name('admin.orders.history');
    Route::get('/orders/search', SearchOrder::class)->name('admin.orders.search');
    Route::get('/orders/{order}', OrderShow::class)->name('admin.orders.show');

    Route::get('/navs-roles', [NavRoleController::class, 'index'])->name('admin.navrole.index');
    Route::get('/settings', SettingsPage::class)->name('admin.settings.index');
    Route::get('/middo-cash', MiddoCashLedgerPage::class)->name('admin.middo-cash');
    Route::get('/cash-positions', CashPositionsBoard::class)->name('admin.cash-positions');
    Route::get('/bank-accounts', BankAccountsPage::class)->name('admin.bank-accounts.index');
    Route::get('/bank-ledger', MiddoBankLedgerPage::class)->name('admin.bank-ledger');
    Route::get('/period-pnl', PeriodPnlPage::class)->name('admin.period-pnl');
    Route::get('/cash-handovers', CashHandovers::class)->name('admin.cash-handovers');
    Route::get('/sla', SlaBoard::class)->name('admin.sla.index');
    Route::get('/riders', RidersBoard::class)->name('admin.riders.index');
    Route::get('/areas', AreasAdmin::class)->name('admin.areas.index');
    Route::get('/coverage', CoverageBoard::class)->name('admin.coverage.index');
    Route::get('/ops-day', OpsDayBoard::class)->name('admin.ops-day.index');
    Route::get('/complaints', Complaints::class)->name('admin.complaints.index');
    Route::get('/complaints/{complaint}', ComplaintShow::class)->name('admin.complaints.show');
    Route::get('/accounts', AccountsHub::class)->name('admin.accounts.index');
    Route::get('/cod-recon', CodDueReconPage::class)->name('admin.cod-recon.index');
    Route::get('/operating-costs', OperatingCostsPage::class)->name('admin.operating-costs.index');
    Route::get('/kitchen-money', KitchenMoneyApprovals::class)->name('admin.kitchen-money.index');
    Route::get('/rider-money', RiderMoneyApprovals::class)->name('admin.rider-money.index');
    Route::get('/custom-runs', CustomRuns::class)->name('admin.custom-runs.index');

    Route::get('/corporates', CorporateTable::class)->name('admin.corporates.index');
    Route::get('/corporates/{corporate}', CorporateShow::class)->name('admin.corporates.show');

    // User Management (one page per role)
    Route::get('/users/admin', fn () => view('admin.users.page', ['role' => 'admin']))->name('admin.users.admin');
    Route::get('/users/operation', fn () => view('admin.users.page', ['role' => 'operation']))->name('admin.users.operation');
    Route::get('/users/accounts', fn () => view('admin.users.page', ['role' => 'accounts']))->name('admin.users.accounts');
    Route::get('/users/kitchen', fn () => view('admin.users.page', ['role' => 'kitchen']))->name('admin.users.kitchen');
    // Legacy corporates URL — same Livewire list (nav may still point here until migrated).
    Route::get('/users/corporate', CorporateTable::class)->name('admin.users.corporate');
    Route::get('/users/delivery', fn () => view('admin.users.page', ['role' => 'delivery']))->name('admin.users.delivery');
    Route::get('/users/ground_marketing', fn () => view('admin.users.page', ['role' => 'ground_marketing']))->name('admin.users.ground_marketing');
    Route::get('/users/{user}', UserShow::class)->whereNumber('user')->name('admin.users.show');
    Route::get('/users/{role?}', fn ($role = null) => view('admin.users.page', ['role' => $role]))->name('admin.users.index');
});
