<?php

namespace App\Livewire\Accounts;

use App\Models\CashHandover;
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
        $proposedRejects = CashHandover::query()
            ->where('target', CashHandover::TARGET_MIDDO)
            ->where('status', CashHandover::STATUS_PROPOSED_REJECT)
            ->count();

        $tiles = array_values(array_filter([
            [
                'label' => 'Accounts Hub',
                'hint' => 'Payables & money flow',
                'route' => $prefix.'.accounts.index',
                'stat' => null,
            ],
            [
                'label' => 'Middo cash',
                'hint' => 'Ledger, adjustments, day-end variance',
                'route' => $prefix.'.middo-cash',
                'stat' => '৳'.number_format($cashBalance),
            ],
            Route::has($prefix.'.bank-ledger') ? [
                'label' => 'Bank ledger',
                'hint' => 'Multi-bank float & EPS net credits',
                'route' => $prefix.'.bank-ledger',
                'stat' => null,
            ] : null,
            Route::has($prefix.'.cash-positions') ? [
                'label' => 'Cash positions',
                'hint' => 'EPS · kitchen · riders · till',
                'route' => $prefix.'.cash-positions',
                'stat' => null,
            ] : null,
            Route::has($prefix.'.period-pnl') ? [
                'label' => 'Period P&L',
                'hint' => 'VAT · middo_rest · opex · Excel',
                'route' => $prefix.'.period-pnl',
                'stat' => null,
            ] : null,
            [
                'label' => 'Cash handovers',
                'hint' => 'Accept Due · confirm reject proposals',
                'route' => $prefix.'.cash-handovers',
                'stat' => $proposedRejects > 0 ? (string) $proposedRejects.' proposed' : null,
            ],
            [
                'label' => 'COD / Due recon',
                'hint' => 'Day × rider Due',
                'route' => $prefix.'.cod-recon.index',
                'stat' => null,
            ],
            [
                'label' => 'Operating costs',
                'hint' => 'Box / custom P&L buckets',
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
            [
                'label' => 'Corporates',
                'hint' => 'Wallet adjust + history',
                'route' => $prefix.'.corporates.index',
                'stat' => null,
            ],
        ], fn (array $tile) => Route::has($tile['route'])));

        $buckets = [
            ['name' => 'Middo cash', 'desc' => 'Physical / ledger cash (MiddoCashLedger SoT)'],
            ['name' => 'middo_rest', 'desc' => 'Order economics residual after partner shares'],
            ['name' => 'Operating costs', 'desc' => 'Box / custom-run commissions and costs'],
            ['name' => 'Partner wallets / Due', 'desc' => 'Kitchen & rider ledgers; rider Due float'],
        ];

        return view('livewire.accounts.dashboard', [
            'tiles' => $tiles,
            'buckets' => $buckets,
        ])->layout('layouts.private.app', ['title' => 'Accounts Dashboard']);
    }
}
