<?php

use App\Livewire\Accounts\Dashboard;
use App\Livewire\Operation\CashHandovers;
use App\Livewire\Shared\AccountsHub;
use App\Livewire\Shared\CashPositionsBoard;
use App\Livewire\Shared\CodDueReconPage;
use App\Livewire\Shared\CorporateShow;
use App\Livewire\Shared\CorporateTable;
use App\Livewire\Shared\KitchenMoneyApprovals;
use App\Livewire\Shared\MiddoBankLedgerPage;
use App\Livewire\Shared\MiddoCashLedgerPage;
use App\Livewire\Shared\OperatingCostsPage;
use App\Livewire\Shared\OrderShow;
use App\Livewire\Shared\RiderMoneyApprovals;
use Illuminate\Support\Facades\Route;

// routes/web/accounts.php — money ownership (A1+) + dual-control handovers (A2)
Route::middleware(['auth', 'role:accounts'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('accounts.dashboard');
    Route::get('/accounts', AccountsHub::class)->name('accounts.accounts.index');
    Route::get('/middo-cash', MiddoCashLedgerPage::class)->name('accounts.middo-cash');
    Route::get('/cash-positions', CashPositionsBoard::class)->name('accounts.cash-positions');
    Route::get('/bank-ledger', MiddoBankLedgerPage::class)->name('accounts.bank-ledger');
    Route::get('/cash-handovers', CashHandovers::class)->name('accounts.cash-handovers');
    Route::get('/cod-recon', CodDueReconPage::class)->name('accounts.cod-recon.index');
    Route::get('/operating-costs', OperatingCostsPage::class)->name('accounts.operating-costs.index');
    Route::get('/kitchen-money', KitchenMoneyApprovals::class)->name('accounts.kitchen-money.index');
    Route::get('/rider-money', RiderMoneyApprovals::class)->name('accounts.rider-money.index');
    Route::get('/corporates', CorporateTable::class)->name('accounts.corporates.index');
    Route::get('/corporates/{corporate}', CorporateShow::class)->name('accounts.corporates.show');
    Route::get('/orders/{order}', OrderShow::class)->name('accounts.orders.show');
});
