<?php

namespace App\Livewire\Shared;

use App\Models\RiderWithdrawalRequest;
use App\Support\MiddoCashLedger;
use App\Support\RiderMoneyService;
use App\Support\StaffPortal;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class RiderMoneyApprovals extends Component
{
    use WithPagination;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);
    }

    public function approveWithdrawal(int $id): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $request = RiderWithdrawalRequest::query()->findOrFail($id);
            $approved = RiderMoneyService::approveWithdrawal($request, (int) Auth::id());
            $this->statusMessage = "Rider withdrawal #{$approved->id} approved for ৳".number_format($approved->amount).'.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not approve withdrawal.';
        }
    }

    public function rejectWithdrawal(int $id): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $request = RiderWithdrawalRequest::query()->findOrFail($id);
            RiderMoneyService::rejectWithdrawal($request, (int) Auth::id(), 'Rejected by ops');
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

        return view('livewire.shared.rider-money-approvals', [
            'withdrawals' => $withdrawals,
            'previews' => $previews,
            'middoCash' => MiddoCashLedger::balance(),
        ])->layout('layouts.private.app', ['title' => 'Rider money']);
    }
}
