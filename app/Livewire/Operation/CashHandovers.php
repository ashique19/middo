<?php

namespace App\Livewire\Operation;

use App\Models\CashHandover;
use App\Support\CashHandoverActions;
use App\Support\MiddoCashLedger;
use App\Support\StaffPortal;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class CashHandovers extends Component
{
    use WithPagination;

    public string $filter = 'pending';

    public ?string $rejectReason = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(StaffPortal::canAcceptHandover() || StaffPortal::canConfirmHandoverReject(), 403);
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function accept(int $handoverId): void
    {
        abort_unless(StaffPortal::canAcceptHandover(), 403);

        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            CashHandoverActions::acceptMiddo(
                CashHandover::query()->findOrFail($handoverId),
                (int) Auth::id()
            );
            $this->statusMessage = "Due handover #{$handoverId} accepted into Middo cash.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept cash handover.';
        }
    }

    public function proposeReject(int $handoverId): void
    {
        abort_unless(StaffPortal::canProposeHandoverReject(), 403);

        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            CashHandoverActions::proposeRejectMiddo(
                CashHandover::query()->findOrFail($handoverId),
                (int) Auth::id(),
                $this->rejectReason
            );
            $this->rejectReason = null;
            $this->statusMessage = "Reject proposed for handover #{$handoverId}. Accounts must confirm.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not propose reject.';
        }
    }

    public function confirmReject(int $handoverId): void
    {
        abort_unless(StaffPortal::canConfirmHandoverReject(), 403);

        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            CashHandoverActions::confirmRejectMiddo(
                CashHandover::query()->findOrFail($handoverId),
                (int) Auth::id(),
                $this->rejectReason
            );
            $this->rejectReason = null;
            $this->statusMessage = "Due handover #{$handoverId} rejected. Rider can re-submit those orders.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not confirm reject.';
        }
    }

    public function dismissProposeReject(int $handoverId): void
    {
        abort_unless(StaffPortal::canConfirmHandoverReject(), 403);

        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            CashHandoverActions::dismissProposeRejectMiddo(
                CashHandover::query()->findOrFail($handoverId),
                (int) Auth::id()
            );
            $this->statusMessage = "Reject proposal dismissed — handover #{$handoverId} is pending again.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not dismiss reject proposal.';
        }
    }

    public function render()
    {
        $filter = in_array($this->filter, ['pending', 'proposed_reject', 'accepted', 'rejected', 'all'], true)
            ? $this->filter
            : 'pending';

        $query = CashHandover::query()
            ->with(['rider', 'items.order', 'acceptedBy', 'rejectionProposedBy'])
            ->where('target', CashHandover::TARGET_MIDDO)
            ->orderByDesc('id');

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        return view('livewire.operation.cash-handovers', [
            'handovers' => $query->paginate(20),
            'middoCashBalance' => MiddoCashLedger::balance(),
            'filter' => $filter,
            'canAccept' => StaffPortal::canAcceptHandover(),
            'canProposeReject' => StaffPortal::canProposeHandoverReject(),
            'canConfirmReject' => StaffPortal::canConfirmHandoverReject(),
        ])->layout('layouts.private.app', ['title' => 'Rider cash handovers']);
    }
}
