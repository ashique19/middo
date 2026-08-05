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

        return view('livewire.shared.rider-money-approvals', [
            'withdrawals' => $withdrawals,
            'previews' => $previews,
            'banks' => $banks,
            'middoCash' => MiddoCashLedger::balance(),
            'canWriteMoney' => StaffPortal::canWriteMoney(),
        ])->layout('layouts.private.app', ['title' => 'Rider money']);
    }
}
