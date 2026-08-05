<?php

namespace App\Livewire\Shared;

use App\Models\KitchenMiddoTransfer;
use App\Models\KitchenWithdrawalRequest;
use App\Models\MiddoBankAccount;
use App\Support\KitchenMoneyService;
use App\Support\MiddoCashLedger;
use App\Support\PayoutChannel;
use App\Support\StaffPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class KitchenMoneyApprovals extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $tab = 'withdrawals';

    public string $statusMessage = '';

    public string $errorMessage = '';

    /** @var array<int|string, int|string|null> */
    public array $approveBankAccountId = [];

    /** @var array<int|string, string> */
    public array $approveReviewNotes = [];

    /** @var array<int|string, mixed> */
    public $approveAttachment = [];

    public function mount(): void
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);
    }

    public function updatingTab(): void
    {
        $this->resetPage();
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    public function approveWithdrawal(int $id): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $request = KitchenWithdrawalRequest::query()->findOrFail($id);
            $channel = (string) ($request->payout_channel ?: PayoutChannel::CASH);

            $rules = [
                "approveReviewNotes.{$id}" => 'nullable|string|max:500',
                "approveAttachment.{$id}" => 'nullable|file|max:4096',
            ];
            if (PayoutChannel::usesBankFloat($channel)) {
                $rules["approveBankAccountId.{$id}"] = 'required|integer|exists:middo_bank_accounts,id';
            }
            $this->validate($rules);

            $approved = KitchenMoneyService::approveWithdrawal(
                $request,
                (int) Auth::id(),
                ($this->approveReviewNotes[$id] ?? '') ?: null,
                [
                    'bank_account_id' => isset($this->approveBankAccountId[$id]) ? (int) $this->approveBankAccountId[$id] : null,
                    'attachment' => $this->approveAttachment[$id] ?? null,
                ]
            );
            unset($this->approveBankAccountId[$id], $this->approveReviewNotes[$id], $this->approveAttachment[$id]);
            $source = PayoutChannel::usesBankFloat($channel) ? 'bank' : 'cash';
            $this->statusMessage = "Withdrawal #{$approved->id} approved for ৳".number_format($approved->amount)." (from Middo {$source}).";
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not approve withdrawal.';
        }
    }

    public function rejectWithdrawal(int $id): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $request = KitchenWithdrawalRequest::query()->findOrFail($id);
            KitchenMoneyService::rejectWithdrawal($request, (int) Auth::id(), 'Rejected by accounts');
            $this->statusMessage = "Withdrawal #{$id} rejected.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reject withdrawal.';
        }
    }

    public function confirmTransfer(int $id): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $transfer = KitchenMiddoTransfer::query()->findOrFail($id);
            KitchenMoneyService::confirmTransfer($transfer, (int) Auth::id());
            $this->statusMessage = "Transfer #{$id} confirmed into Middo cash.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not confirm transfer.';
        }
    }

    public function rejectTransfer(int $id): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $transfer = KitchenMiddoTransfer::query()->findOrFail($id);
            KitchenMoneyService::rejectTransfer($transfer, (int) Auth::id(), 'Rejected by accounts');
            $this->statusMessage = "Transfer #{$id} rejected.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reject transfer.';
        }
    }

    public function render()
    {
        $withdrawals = KitchenWithdrawalRequest::query()
            ->with('kitchen')
            ->when($this->tab === 'withdrawals', fn ($q) => $q->where('status', KitchenWithdrawalRequest::STATUS_PENDING))
            ->latest('id')
            ->paginate(15, ['*'], 'withdrawalsPage');

        $transfers = KitchenMiddoTransfer::query()
            ->with('kitchen')
            ->when($this->tab === 'transfers', fn ($q) => $q->where('status', KitchenMiddoTransfer::STATUS_PENDING))
            ->latest('id')
            ->paginate(15, ['*'], 'transfersPage');

        $previews = [];
        if ($this->tab === 'withdrawals') {
            foreach ($withdrawals as $w) {
                if ($w->status === KitchenWithdrawalRequest::STATUS_PENDING) {
                    $previews[$w->id] = KitchenMoneyService::withdrawalPreview($w);
                }
            }
        }

        $banks = MiddoBankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('livewire.shared.kitchen-money-approvals', [
            'withdrawals' => $withdrawals,
            'transfers' => $transfers,
            'previews' => $previews,
            'banks' => $banks,
            'middoCash' => MiddoCashLedger::balance(),
            'canWriteMoney' => StaffPortal::canWriteMoney(),
        ])->layout('layouts.private.app', ['title' => 'Kitchen money']);
    }
}
