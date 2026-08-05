<?php

namespace App\Livewire\Shared;

use App\Models\MiddoBankAccount;
use App\Support\CashPositions;
use App\Support\StaffPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class CashPositionsBoard extends Component
{
    public string $depositAmount = '';

    public string $depositReason = '';

    public ?int $depositBankAccountId = null;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);
        $default = MiddoBankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
        $this->depositBankAccountId = $default ? (int) $default : null;
    }

    public function depositTillToBank(): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);

        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            CashPositions::depositTillToBank(
                (int) $this->depositBankAccountId,
                (int) $this->depositAmount,
                $this->depositReason,
                (int) Auth::id()
            );
            $this->statusMessage = 'Deposited ৳'.number_format((int) $this->depositAmount).' from till to bank.';
            $this->depositAmount = '';
            $this->depositReason = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not post till→bank deposit.';
        }
    }

    public function render()
    {
        $prefix = StaffPortal::rolePrefix();
        $snapshot = CashPositions::snapshot();
        $banks = MiddoBankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $links = [
            'kitchen_money' => Route::has($prefix.'.kitchen-money.index') ? route($prefix.'.kitchen-money.index') : null,
            'cash_handovers' => Route::has($prefix.'.cash-handovers') ? route($prefix.'.cash-handovers') : null,
            'middo_cash' => Route::has($prefix.'.middo-cash') ? route($prefix.'.middo-cash') : null,
            'bank_ledger' => Route::has($prefix.'.bank-ledger') ? route($prefix.'.bank-ledger') : null,
            'cod_recon' => Route::has($prefix.'.cod-recon.index') ? route($prefix.'.cod-recon.index') : null,
        ];

        return view('livewire.shared.cash-positions-board', [
            'snapshot' => $snapshot,
            'banks' => $banks,
            'links' => $links,
            'canWrite' => StaffPortal::canWriteMoney(),
        ])->layout('layouts.private.app', ['title' => 'Cash positions']);
    }
}
