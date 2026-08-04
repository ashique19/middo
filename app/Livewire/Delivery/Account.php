<?php

namespace App\Livewire\Delivery;

use App\Models\PartnerPayable;
use App\Models\RiderAccountLedgerEntry;
use App\Models\RiderWithdrawalRequest;
use App\Support\RiderAccountLedger;
use App\Support\RiderCommission;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Account extends Component
{
    use WithPagination;

    public string $tab = 'statement';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public $withdrawAmount = null;

    public string $withdrawNotes = '';

    public function updatingTab(): void
    {
        $this->resetPage();
        $this->errorMessage = '';
        $this->statusMessage = '';
    }

    public function requestWithdrawal(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
        $riderId = (int) Auth::id();

        try {
            $this->validate([
                'withdrawAmount' => 'required|integer|min:1',
                'withdrawNotes' => 'nullable|string|max:500',
            ]);

            $amount = (int) $this->withdrawAmount;
            $wallet = RiderAccountLedger::balance($riderId);
            $due = (int) Auth::user()?->balance;

            if ($due > 0) {
                throw new \RuntimeException('Hand over Due to Middo cash first, then request payment.');
            }
            if ($wallet < 1) {
                throw new \RuntimeException('Nothing to withdraw — Middo does not currently owe you.');
            }
            if ($amount > $wallet) {
                throw new \RuntimeException("Requested ৳{$amount} exceeds wallet ৳{$wallet}.");
            }

            if (RiderWithdrawalRequest::query()
                ->where('rider_user_id', $riderId)
                ->where('status', RiderWithdrawalRequest::STATUS_PENDING)
                ->exists()) {
                throw new \RuntimeException('You already have a pending withdrawal request.');
            }

            RiderWithdrawalRequest::create([
                'rider_user_id' => $riderId,
                'amount' => $amount,
                'status' => RiderWithdrawalRequest::STATUS_PENDING,
                'notes' => $this->withdrawNotes ?: null,
            ]);

            $this->withdrawAmount = null;
            $this->withdrawNotes = '';
            $this->statusMessage = 'Payment request submitted for Middo approval.';
            $this->tab = 'withdrawals';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not submit withdrawal.';
        }
    }

    public function render()
    {
        $rider = Auth::user();
        $riderId = (int) $rider->id;
        $wallet = RiderAccountLedger::balance($riderId);
        $due = (int) $rider->balance;

        $openPayables = PartnerPayable::query()
            ->with('order:id,delivery_date,menu_item_id')
            ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
            ->where('beneficiary_user_id', $riderId)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->where('amount', '>', 0)
            ->orderBy('id')
            ->get();

        $statement = RiderAccountLedgerEntry::query()
            ->where('rider_user_id', $riderId)
            ->latest('id')
            ->paginate(15, ['*'], 'statementPage');

        $commissionEntries = RiderAccountLedgerEntry::query()
            ->where('rider_user_id', $riderId)
            ->whereIn('entry_type', ['commission_accrued', 'commission_settled_in_kind', 'share_voided', 'withdrawal_paid'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->filter(fn (RiderAccountLedgerEntry $row) => RiderCommission::shouldShow(abs((int) $row->amount)));

        $withdrawals = RiderWithdrawalRequest::query()
            ->where('rider_user_id', $riderId)
            ->latest('id')
            ->paginate(10, ['*'], 'withdrawalsPage');

        return view('livewire.delivery.account', [
            'wallet' => $wallet,
            'due' => $due,
            'openPayables' => $openPayables,
            'openPayableTotal' => (int) $openPayables->sum('amount'),
            'statement' => $statement,
            'commissionEntries' => $commissionEntries,
            'withdrawals' => $withdrawals,
            'canRequestPayment' => $wallet > 0 && $due === 0,
        ])->layout('delivery.layout.app', ['title' => 'Rider Account']);
    }
}
