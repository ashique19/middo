<?php

namespace App\Livewire\Accounts;

use App\Support\MiddoCashLedger;
use App\Support\StaffPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->role?->name === 'accounts', 403);
    }

    public function render()
    {
        $prefix = StaffPortal::rolePrefix('accounts');
        $cashBalance = MiddoCashLedger::balance();

        $tiles = array_values(array_filter([
            [
                'label' => 'Accounts Hub',
                'hint' => 'Payables & money flow',
                'route' => $prefix.'.accounts.index',
                'stat' => null,
            ],
            [
                'label' => 'Middo cash',
                'hint' => 'Cash ledger + adjustments',
                'route' => $prefix.'.middo-cash',
                'stat' => '৳'.number_format($cashBalance),
            ],
            [
                'label' => 'COD / Due recon',
                'hint' => 'Day × rider Due',
                'route' => $prefix.'.cod-recon.index',
                'stat' => null,
            ],
            [
                'label' => 'Operating costs',
                'hint' => 'Box / custom P&L',
                'route' => $prefix.'.operating-costs.index',
                'stat' => null,
            ],
            [
                'label' => 'Kitchen money',
                'hint' => 'Withdrawal approvals',
                'route' => $prefix.'.kitchen-money.index',
                'stat' => null,
            ],
            [
                'label' => 'Rider money',
                'hint' => 'Withdrawal approvals',
                'route' => $prefix.'.rider-money.index',
                'stat' => null,
            ],
        ], fn (array $tile) => Route::has($tile['route'])));

        return view('livewire.accounts.dashboard', [
            'tiles' => $tiles,
        ])->layout('layouts.private.app', ['title' => 'Accounts Dashboard']);
    }
}
