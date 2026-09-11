<?php

namespace App\Livewire\Shared;

use App\Models\MiddoBankAccount;
use App\Models\RiderWithdrawalRequest;
use App\Support\MiddoCashLedger;
use App\Support\PayoutChannel;
use App\Support\RiderMoneyService;
use App\Support\StaffPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class RiderMoneyApprovals extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public ?int $adjustRiderId = null;

    public string $adjustDirection = 'credit';

    public string $adjustAmount = '';

    public string $adjustReason = '';

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

    public function approveWithdrawal(int $id): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $request = RiderWithdrawalRequest::query()->findOrFail($id);
            $channel = (string) ($request->payout_channel ?: PayoutChannel::CASH);

            $rules = [
                "approveReviewNotes.{$id}" => 'nullable|string|max:500',
                "approveAttachment.{$id}" => 'nullable|file|max:4096',
            ];
            if (PayoutChannel::usesBankFloat($channel)) {
                $rules["approveBankAccountId.{$id}"] = 'required|integer|exists:middo_bank_accounts,id';
            }
            $this->validate($rules);

            $approved = RiderMoneyService::approveWithdrawal(
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
            $this->statusMessage = "Rider withdrawal #{$approved->id} approved for ৳".number_format($approved->amount)." (from Middo {$source}).";
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
            $request = RiderWithdrawalRequest::query()->findOrFail($id);
            RiderMoneyService::rejectWithdrawal($request, (int) Auth::id(), 'Rejected by accounts');
            $this->statusMessage = "Rider withdrawal #{$id} rejected.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reject withdrawal.';
        }
    }


    public function adjustCommission(): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'adjustRiderId' => 'required|integer|exists:users,id',
            'adjustDirection' => 'required|in:credit,debit',
            'adjustAmount' => 'required|integer|min:1|max:100000',
            'adjustReason' => 'required|string|min:3|max:500',
        ]);

        try {
            $rider = \App\Models\User::query()->findOrFail((int) $this->adjustRiderId);
            if (! $rider->isDelivery()) {
                throw new \RuntimeException('Select a delivery rider.');
            }

            $amount = (int) $this->adjustAmount;
            $reason = trim($this->adjustReason);
            if ($this->adjustDirection === 'credit') {
                \App\Support\RiderAccountLedger::credit(
                    (int) $rider->id,
                    $amount,
                    'commission_adjustment',
                    'manual_adjustment',
                    null,
                    $reason,
                    (int) \Illuminate\Support\Facades\Auth::id(),
                );
                $this->statusMessage = "Credited ৳{$amount} to {$rider->name}'s rider wallet.";
            } else {
                \App\Support\RiderAccountLedger::debit(
                    (int) $rider->id,
                    $amount,
                    'commission_adjustment',
                    'manual_adjustment',
                    null,
                    $reason,
                    (int) \Illuminate\Support\Facades\Auth::id(),
                );
                $this->statusMessage = "Debited ৳{$amount} from {$rider->name}'s rider wallet.";
            }

            $this->reset(['adjustRiderId', 'adjustAmount', 'adjustReason']);
            $this->adjustDirection = 'credit';
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not adjust rider commission.';
        }
    }

    public function render()
    {
        $withdrawals = RiderWithdrawalRequest::query()
            ->with('rider')
            ->where('status', RiderWithdrawalRequest::STATUS_PENDING)
            ->latest('id')
            ->paginate(15);

        $previews = [];
        foreach ($withdrawals as $w) {
            $previews[$w->id] = RiderMoneyService::withdrawalPreview($w);
        }

        $banks = MiddoBankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

                $riders = \App\Models\User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'delivery'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'mobile']);

        return view('livewire.shared.rider-money-approvals', [
            'riders' => $riders,
            'withdrawals' => $withdrawals,
            'previews' => $previews,
            'banks' => $banks,
            'middoCash' => MiddoCashLedger::balance(),
            'canWriteMoney' => StaffPortal::canWriteMoney(),
        ])->layout('layouts.private.app', ['title' => 'Rider money']);
    }
}
