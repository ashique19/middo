<?php

namespace App\Livewire\Shared;

use App\Models\MiddoCashLedgerEntry;
use App\Support\MiddoCashLedger;
use App\Support\StaffPortal;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MiddoCashLedgerPage extends Component
{
    use WithPagination;

    /** all|package|adjustment */
    public string $entryFilter = 'all';

    public string $adjustDirection = 'credit';

    public string $adjustAmount = '';

    public string $adjustReason = '';

    public string $countedCash = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);
    }

    public function updatingEntryFilter(): void
    {
        $this->resetPage();
    }

    public function postAdjustment(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        $amount = (int) $this->adjustAmount;
        $reason = trim($this->adjustReason);

        if ($amount < 1) {
            $this->errorMessage = 'Enter a positive adjustment amount.';

            return;
        }

        if ($reason === '') {
            $this->errorMessage = 'A reason is required for cash adjustments.';

            return;
        }

        if (! in_array($this->adjustDirection, ['credit', 'debit'], true)) {
            $this->errorMessage = 'Choose credit or debit.';

            return;
        }

        try {
            $actorId = (int) Auth::id();
            $description = 'Adjustment: '.$reason;
            if ($this->adjustDirection === 'credit') {
                MiddoCashLedger::credit($amount, 'adjustment', null, null, $description, $actorId);
            } else {
                MiddoCashLedger::debit($amount, 'adjustment', null, null, $description, $actorId);
            }

            $this->statusMessage = ucfirst($this->adjustDirection)." ৳".number_format($amount).' posted.';
            $this->adjustAmount = '';
            $this->adjustReason = '';
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not post adjustment.';
        }
    }

    public function render()
    {
        $entries = MiddoCashLedgerEntry::query()
            ->with('createdByUser')
            ->when($this->entryFilter === 'package', function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', '%Package%')
                        ->orWhere('description', 'like', '%package%');
                });
            })
            ->when($this->entryFilter === 'adjustment', fn ($q) => $q->where('entry_type', 'adjustment'))
            ->orderByDesc('id')
            ->paginate(30);

        $balance = MiddoCashLedger::balance();
        $counted = $this->countedCash !== '' ? (int) $this->countedCash : null;
        $variance = $counted !== null ? $counted - $balance : null;

        return view('livewire.shared.middo-cash-ledger-page', [
            'entries' => $entries,
            'balance' => $balance,
            'variance' => $variance,
        ])->layout('layouts.private.app', ['title' => 'Middo cash']);
    }
}
