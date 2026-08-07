<?php

namespace App\Livewire\Kitchen;

use App\Models\CashHandover;
use App\Models\KitchenAccountLedgerEntry;
use App\Models\KitchenMiddoTransfer;
use App\Models\KitchenWithdrawalRequest;
use App\Models\PartnerPayable;
use App\Support\KitchenAccountLedger;
use App\Support\PayoutChannel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Account extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $tab = 'statement';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public $withdrawAmount = null;

    public string $withdrawNotes = '';

    public string $payoutChannel = PayoutChannel::CASH;

    public $transferAmount = null;

    public string $transferReference = '';

    public string $transferNotes = '';

    public $transferProof = null;

    public function mount(): void
    {
        $this->payoutChannel = Auth::user()?->preferredPayoutChannel() ?? PayoutChannel::CASH;
    }

    public function updatingTab(): void
    {
        $this->resetPage();
        $this->errorMessage = '';
        $this->statusMessage = '';
    }

    public function updatedPayoutChannel(): void
    {
        $this->resetValidation();
    }

    public function requestWithdrawal(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
        $kitchenId = (int) Auth::id();

        try {
            $this->validate([
                'withdrawAmount' => 'required|integer|min:1',
                'withdrawNotes' => 'nullable|string|max:500',
                'payoutChannel' => 'required|in:'.implode(',', PayoutChannel::all()),
            ]);

            $user = Auth::user();
            if (! $user) {
                throw new \RuntimeException('You must be logged in.');
            }

            if (! $user->hasCompletePayoutMethod($this->payoutChannel)) {
                throw new \RuntimeException(
                    'Add your '.PayoutChannel::label($this->payoutChannel).' details in profile before requesting this payout.'
                );
            }

            $details = $user->payoutDetailsFor($this->payoutChannel);
            PayoutChannel::assertValid($this->payoutChannel, $details);

            $amount = (int) $this->withdrawAmount;
            $balance = KitchenAccountLedger::balance($kitchenId);
            if ($balance < 1) {
                throw new \RuntimeException('Nothing to withdraw — Middo does not currently owe you.');
            }
            if ($amount > $balance) {
                throw new \RuntimeException("Requested ৳{$amount} exceeds what Middo owes you (৳{$balance}).");
            }

            if (KitchenWithdrawalRequest::query()
                ->where('kitchen_user_id', $kitchenId)
                ->where('status', KitchenWithdrawalRequest::STATUS_PENDING)
                ->exists()) {
                throw new \RuntimeException('You already have a pending withdrawal request.');
            }

            KitchenWithdrawalRequest::create([
                'kitchen_user_id' => $kitchenId,
                'amount' => $amount,
                'status' => KitchenWithdrawalRequest::STATUS_PENDING,
                'notes' => $this->withdrawNotes ?: null,
                'payout_channel' => $this->payoutChannel,
                'payout_details' => $details ?: null,
            ]);

            $this->withdrawAmount = null;
            $this->withdrawNotes = '';
            $this->payoutChannel = $user->preferredPayoutChannel();
            $this->statusMessage = 'Withdrawal request submitted for Middo approval.';
            $this->tab = 'withdrawals';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not submit withdrawal.';
        }
    }

    public function submitTransfer(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
        $kitchenId = (int) Auth::id();

        try {
            $this->validate([
                'transferAmount' => 'required|integer|min:1',
                'transferReference' => 'nullable|string|max:100',
                'transferNotes' => 'nullable|string|max:500',
                'transferProof' => 'required|image|max:4096',
            ]);

            $transfer = KitchenMiddoTransfer::create([
                'kitchen_user_id' => $kitchenId,
                'amount' => (int) $this->transferAmount,
                'status' => KitchenMiddoTransfer::STATUS_PENDING,
                'reference_code' => $this->transferReference ?: null,
                'notes' => $this->transferNotes ?: null,
            ]);

            $transfer->update([
                'proof_path' => $this->storeTransferProof($transfer),
            ]);

            $this->reset('transferAmount', 'transferReference', 'transferNotes', 'transferProof');
            $this->statusMessage = 'Transfer submitted with proof. Waiting for Middo confirmation.';
            $this->tab = 'transfers';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not submit transfer.';
        }
    }

    protected function storeTransferProof(KitchenMiddoTransfer $transfer): string
    {
        $relativePath = 'img/kitchen-transfers';
        $directory = public_path($relativePath);
        File::ensureDirectoryExists($directory);

        $extension = strtolower($this->transferProof->extension() ?: 'jpg');
        $filename = "transfer-{$transfer->id}.{$extension}";
        $destination = $directory.DIRECTORY_SEPARATOR.$filename;

        $sourcePath = $this->transferProof->getRealPath();
        if (! $sourcePath || ! is_readable($sourcePath)) {
            throw new \RuntimeException('Uploaded proof is no longer available. Please try again.');
        }

        if (file_exists($destination)) {
            File::delete($destination);
        }

        if (! File::copy($sourcePath, $destination)) {
            throw new \RuntimeException('Could not save the uploaded proof. Please try again.');
        }

        return $relativePath.'/'.$filename;
    }

    public function render()
    {
        $kitchenId = (int) Auth::id();
        $balance = KitchenAccountLedger::balance($kitchenId);

        $openPayables = PartnerPayable::query()
            ->with('order:id,delivery_date,menu_item_id')
            ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
            ->where('beneficiary_user_id', $kitchenId)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->orderBy('id')
            ->get();

        $statement = KitchenAccountLedgerEntry::query()
            ->where('kitchen_user_id', $kitchenId)
            ->latest('id')
            ->paginate(15, ['*'], 'statementPage');

        $withdrawals = KitchenWithdrawalRequest::query()
            ->where('kitchen_user_id', $kitchenId)
            ->latest('id')
            ->paginate(10, ['*'], 'withdrawalsPage');

        $transfers = KitchenMiddoTransfer::query()
            ->where('kitchen_user_id', $kitchenId)
            ->latest('id')
            ->paginate(10, ['*'], 'transfersPage');

        $pendingCashHandovers = CashHandover::query()
            ->with(['items.order.orderGroup'])
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('target', CashHandover::TARGET_KITCHEN)
                    ->orWhereNull('target');
            })
            ->get()
            ->filter(function (CashHandover $handover) use ($kitchenId) {
                $handover->loadMissing('items.order.orderGroup');
                if ($handover->items->isEmpty()) {
                    return false;
                }

                return $handover->items->every(
                    fn ($item) => (int) ($item->order?->orderGroup?->kitchen_id) === $kitchenId
                );
            })
            ->count();

        if ($this->tab === 'withdraw' && $balance < 1) {
            $this->tab = 'statement';
        }
        if ($this->tab === 'send' && $balance >= 0) {
            $this->tab = 'statement';
        }

        return view('livewire.kitchen.account', [
            'balance' => $balance,
            'openPayables' => $openPayables,
            'openPayableTotal' => (int) $openPayables->sum('amount'),
            'statement' => $statement,
            'withdrawals' => $withdrawals,
            'transfers' => $transfers,
            'pendingCashHandovers' => $pendingCashHandovers,
        ])->layout('kitchen.layout.app', ['title' => 'Kitchen Account']);
    }
}
