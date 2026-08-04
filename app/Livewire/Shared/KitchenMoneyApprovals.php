<?php

namespace App\Livewire\Shared;

use App\Models\KitchenMiddoTransfer;
use App\Models\KitchenWithdrawalRequest;
use App\Support\KitchenMoneyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class KitchenMoneyApprovals extends Component
{
    use WithPagination;

    public string $tab = 'withdrawals';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
    }

    public function updatingTab(): void
    {
        $this->resetPage();
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    public function approveWithdrawal(int $id): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $request = KitchenWithdrawalRequest::query()->findOrFail($id);
            $approved = KitchenMoneyService::approveWithdrawal($request, (int) Auth::id());
            $this->statusMessage = "Withdrawal #{$approved->id} approved for ৳".number_format($approved->amount).'.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not approve withdrawal.';
        }
    }

    public function rejectWithdrawal(int $id): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $request = KitchenWithdrawalRequest::query()->findOrFail($id);
            KitchenMoneyService::rejectWithdrawal($request, (int) Auth::id(), 'Rejected by ops');
            $this->statusMessage = "Withdrawal #{$id} rejected.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reject withdrawal.';
        }
    }

    public function confirmTransfer(int $id): void
    {
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
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $transfer = KitchenMiddoTransfer::query()->findOrFail($id);
            KitchenMoneyService::rejectTransfer($transfer, (int) Auth::id(), 'Rejected by ops');
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

        return view('livewire.shared.kitchen-money-approvals', [
            'withdrawals' => $withdrawals,
            'transfers' => $transfers,
        ])->layout('layouts.private.app', ['title' => 'Kitchen money']);
    }
}
