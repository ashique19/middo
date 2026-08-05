<?php

namespace App\Livewire\Kitchen;

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

    public string $payoutBankName = '';

    public string $payoutAccountName = '';

    public string $payoutAccountNumber = '';

    public string $payoutMobile = '';

    public $transferAmount = null;

    public string $transferReference = '';

    public string $transferNotes = '';

    public $transferProof = null;

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
            $rules = [
                'withdrawAmount' => 'required|integer|min:1',
                'withdrawNotes' => 'nullable|string|max:500',
                'payoutChannel' => 'required|in:'.implode(',', PayoutChannel::all()),
            ];
            $rules = array_merge($rules, $this->payoutDetailRules());
            $this->validate($rules);

            $details = $this->buildPayoutDetails();
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
            $this->resetPayoutFields();
            $this->statusMessage = 'Withdrawal request submitted for Middo approval.';
            $this->tab = 'withdrawals';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not submit withdrawal.';
        }
    }

    /**
     * @return array<string, string>
     */
    protected function payoutDetailRules(): array
    {
        return match ($this->payoutChannel) {
            PayoutChannel::BANK => [
                'payoutBankName' => 'nullable|string|max:120',
                'payoutAccountName' => 'nullable|string|max:120',
                'payoutAccountNumber' => 'required|string|max:64',
            ],
            PayoutChannel::BKASH, PayoutChannel::NAGAD => [
                'payoutAccountName' => 'nullable|string|max:120',
                'payoutMobile' => 'required|string|max:32',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function buildPayoutDetails(): array
    {
        return PayoutChannel::normalizeDetails($this->payoutChannel, [
            'bank_name' => $this->payoutBankName,
            'account_name' => $this->payoutAccountName,
            'account_number' => $this->payoutAccountNumber,
            'mobile' => $this->payoutMobile,
        ]);
    }

    protected function resetPayoutFields(): void
    {
        $this->payoutChannel = PayoutChannel::CASH;
        $this->payoutBankName = '';
        $this->payoutAccountName = '';
        $this->payoutAccountNumber = '';
        $this->payoutMobile = '';
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

        return view('livewire.kitchen.account', [
            'balance' => $balance,
            'openPayables' => $openPayables,
            'openPayableTotal' => (int) $openPayables->sum('amount'),
            'statement' => $statement,
            'withdrawals' => $withdrawals,
            'transfers' => $transfers,
        ])->layout('kitchen.layout.app', ['title' => 'Kitchen Account']);
    }
}
