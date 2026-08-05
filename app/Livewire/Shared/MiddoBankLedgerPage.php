<?php

namespace App\Livewire\Shared;

use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use App\Support\MiddoBankLedger;
use App\Support\StaffPortal;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MiddoBankLedgerPage extends Component
{
    use WithPagination;

    public ?int $accountFilter = null;

    public string $adjustDirection = 'credit';

    public string $adjustAmount = '';

    public string $adjustReason = '';

    public ?int $adjustAccountId = null;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);
        $default = MiddoBankLedger::defaultAccount();
        $this->adjustAccountId = $default?->id;
        $this->accountFilter = $default?->id;
    }

    public function updatingAccountFilter(): void
    {
        $this->resetPage();
    }

    public function postAdjustment(): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);

        $this->statusMessage = '';
        $this->errorMessage = '';

        $amount = (int) $this->adjustAmount;
        $reason = trim($this->adjustReason);
        $accountId = (int) $this->adjustAccountId;

        if ($accountId < 1) {
            $this->errorMessage = 'Select a bank account.';

            return;
        }

        if ($amount < 1) {
            $this->errorMessage = 'Enter a positive adjustment amount.';

            return;
        }

        if ($reason === '') {
            $this->errorMessage = 'A reason is required for bank adjustments.';

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
                MiddoBankLedger::credit($accountId, $amount, MiddoBankLedgerEntry::TYPE_ADJUSTMENT, null, null, $description, $actorId);
            } else {
                MiddoBankLedger::debit($accountId, $amount, MiddoBankLedgerEntry::TYPE_ADJUSTMENT, null, null, $description, $actorId);
            }

            $this->statusMessage = ucfirst($this->adjustDirection).' ৳'.number_format($amount).' posted.';
            $this->adjustAmount = '';
            $this->adjustReason = '';
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not post adjustment.';
        }
    }

    public function render()
    {
        $accounts = MiddoBankAccount::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $balances = $accounts->mapWithKeys(
            fn (MiddoBankAccount $a) => [$a->id => MiddoBankLedger::balance((int) $a->id)]
        );

        $entries = MiddoBankLedgerEntry::query()
            ->with(['bankAccount', 'createdByUser'])
            ->when($this->accountFilter, fn ($q) => $q->where('middo_bank_account_id', $this->accountFilter))
            ->orderByDesc('id')
            ->paginate(30);

        $role = Auth::user()?->role?->name;
        $layout = $role === 'accounts' ? 'layouts.private.app' : 'layouts.private.app';

        return view('livewire.shared.middo-bank-ledger-page', [
            'accounts' => $accounts,
            'balances' => $balances,
            'entries' => $entries,
            'canWrite' => StaffPortal::canWriteMoney(),
        ])->layout($layout, ['title' => 'Bank ledger']);
    }
}
