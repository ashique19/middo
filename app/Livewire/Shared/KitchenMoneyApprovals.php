<?php

namespace App\Livewire\Shared;

use App\Models\KitchenMiddoTransfer;
use App\Models\KitchenSettlementBatch;
use App\Models\KitchenWithdrawalRequest;
use App\Models\MiddoBankAccount;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenAccountLedger;
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

    public ?int $batchKitchenId = null;

    public string $batchName = '';

    public string $batchNotes = '';

    public string $batchPayoutChannel = PayoutChannel::CASH;

    public string $batchPayoutBankName = '';

    public string $batchPayoutAccountName = '';

    public string $batchPayoutAccountNumber = '';

    public string $batchPayoutMobile = '';

    /** @var list<int|string> */
    public array $batchPayableIds = [];

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

    public function updatedBatchKitchenId(): void
    {
        $this->batchPayableIds = [];
        $this->resetValidation();
    }

    public function updatedBatchPayoutChannel(): void
    {
        $this->resetValidation();
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

    public function createBatch(): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $rules = [
                'batchKitchenId' => 'required|integer|exists:users,id',
                'batchName' => 'required|string|max:120',
                'batchNotes' => 'nullable|string|max:500',
                'batchPayoutChannel' => 'required|in:'.implode(',', PayoutChannel::all()),
                'batchPayableIds' => 'required|array|min:1',
                'batchPayableIds.*' => 'integer',
            ];
            if ($this->batchPayoutChannel === PayoutChannel::BANK) {
                $rules['batchPayoutAccountNumber'] = 'required|string|max:64';
            }
            if (in_array($this->batchPayoutChannel, [PayoutChannel::BKASH, PayoutChannel::NAGAD], true)) {
                $rules['batchPayoutMobile'] = 'required|string|max:32';
            }
            $this->validate($rules);

            $details = PayoutChannel::normalizeDetails($this->batchPayoutChannel, [
                'bank_name' => $this->batchPayoutBankName,
                'account_name' => $this->batchPayoutAccountName,
                'account_number' => $this->batchPayoutAccountNumber,
                'mobile' => $this->batchPayoutMobile,
            ]);

            $batch = KitchenMoneyService::createSettlementBatch(
                (int) $this->batchKitchenId,
                $this->batchName,
                array_map('intval', $this->batchPayableIds),
                $this->batchPayoutChannel,
                $details,
                (int) Auth::id(),
                $this->batchNotes ?: null,
            );

            $this->batchName = '';
            $this->batchNotes = '';
            $this->batchPayableIds = [];
            $this->batchPayoutChannel = PayoutChannel::CASH;
            $this->batchPayoutBankName = '';
            $this->batchPayoutAccountName = '';
            $this->batchPayoutAccountNumber = '';
            $this->batchPayoutMobile = '';
            $this->statusMessage = "Settlement batch #{$batch->id} created for ৳".number_format($batch->amount).'.';
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not create settlement batch.';
        }
    }

    public function approveBatch(int $id): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $batch = KitchenSettlementBatch::query()->findOrFail($id);
            $channel = (string) ($batch->payout_channel ?: PayoutChannel::CASH);

            $rules = [
                "approveReviewNotes.{$id}" => 'nullable|string|max:500',
                "approveAttachment.{$id}" => 'nullable|file|max:4096',
            ];
            if (PayoutChannel::usesBankFloat($channel)) {
                $rules["approveBankAccountId.{$id}"] = 'required|integer|exists:middo_bank_accounts,id';
            }
            $this->validate($rules);

            $approved = KitchenMoneyService::approveSettlementBatch(
                $batch,
                (int) Auth::id(),
                ($this->approveReviewNotes[$id] ?? '') ?: null,
                [
                    'bank_account_id' => isset($this->approveBankAccountId[$id]) ? (int) $this->approveBankAccountId[$id] : null,
                    'attachment' => $this->approveAttachment[$id] ?? null,
                ]
            );
            unset($this->approveBankAccountId[$id], $this->approveReviewNotes[$id], $this->approveAttachment[$id]);
            $source = PayoutChannel::usesBankFloat($channel) ? 'bank' : 'cash';
            $this->statusMessage = "Settlement batch #{$approved->id} paid ৳".number_format($approved->amount)." (from Middo {$source}).";
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not approve settlement batch.';
        }
    }

    public function rejectBatch(int $id): void
    {
        abort_unless(StaffPortal::canWriteMoney(), 403);
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $batch = KitchenSettlementBatch::query()->findOrFail($id);
            KitchenMoneyService::rejectSettlementBatch($batch, (int) Auth::id(), 'Rejected by accounts');
            $this->statusMessage = "Settlement batch #{$id} rejected; payables released.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reject settlement batch.';
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

        $batches = KitchenSettlementBatch::query()
            ->with(['kitchen', 'items'])
            ->when($this->tab === 'batches', fn ($q) => $q->where('status', KitchenSettlementBatch::STATUS_PENDING))
            ->latest('id')
            ->paginate(15, ['*'], 'batchesPage');

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

        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        $kitchens = $kitchenRoleId
            ? User::query()->where('role_id', $kitchenRoleId)->where('status', 'active')->orderBy('first_name')->get()
            : collect();

        $availablePayables = collect();
        $kitchenWallet = 0;
        if ($this->tab === 'batches' && $this->batchKitchenId) {
            $reserved = KitchenMoneyService::reservedPayableIds((int) $this->batchKitchenId);
            $availablePayables = PartnerPayable::query()
                ->with('order:id,delivery_date')
                ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
                ->where('beneficiary_user_id', (int) $this->batchKitchenId)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->when($reserved !== [], fn ($q) => $q->whereNotIn('id', $reserved))
                ->orderBy('id')
                ->get();
            $kitchenWallet = KitchenAccountLedger::balance((int) $this->batchKitchenId);
        }

        return view('livewire.shared.kitchen-money-approvals', [
            'withdrawals' => $withdrawals,
            'transfers' => $transfers,
            'batches' => $batches,
            'previews' => $previews,
            'banks' => $banks,
            'kitchens' => $kitchens,
            'availablePayables' => $availablePayables,
            'kitchenWallet' => $kitchenWallet,
            'middoCash' => MiddoCashLedger::balance(),
            'canWriteMoney' => StaffPortal::canWriteMoney(),
        ])->layout('layouts.private.app', ['title' => 'Kitchen money']);
    }
}
