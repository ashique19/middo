<?php

namespace App\Livewire\Operation;

use App\Models\CashHandover;
use App\Models\User;
use App\Support\CashHandoverActions;
use App\Support\MiddoCashLedger;
use App\Support\OrderMoneyFlow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CashHandovers extends Component
{
    use WithPagination;

    public string $filter = 'pending';

    public ?string $rejectReason = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function accept(int $handoverId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $actorId = (int) Auth::id();

        try {
            DB::transaction(function () use ($handoverId, $actorId) {
                $handover = CashHandover::query()
                    ->with('items.order')
                    ->whereKey($handoverId)
                    ->lockForUpdate()
                    ->first();

                if (! $handover || ! $handover->isPending()) {
                    throw new \RuntimeException('This cash handover is no longer pending.');
                }

                if (! $handover->isMiddoTarget()) {
                    throw new \RuntimeException('This handover is for kitchen, not Middo/ops.');
                }

                $rider = User::query()->whereKey($handover->rider_id)->lockForUpdate()->firstOrFail();

                if ((int) $rider->balance < (int) $handover->amount) {
                    throw new \RuntimeException('Rider Due balance is insufficient for this handover.');
                }

                $rider->decrement('balance', (int) $handover->amount);

                MiddoCashLedger::credit(
                    (int) $handover->amount,
                    'rider_cash_handover',
                    CashHandover::class,
                    $handover->id,
                    "Due cash handover #{$handover->id} from rider #{$rider->id}",
                    $actorId
                );

                $handover->update([
                    'status' => 'accepted',
                    'accepted_by' => $actorId,
                    'accepted_at' => now(),
                ]);

                OrderMoneyFlow::recordCashHandoverToMiddo($handover->fresh(['items.order']), $actorId);
            });

            $this->statusMessage = "Due handover #{$handoverId} accepted into Middo cash.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept cash handover.';
        }
    }

    public function reject(int $handoverId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $actorId = (int) Auth::id();

        try {
            $handover = CashHandover::query()->whereKey($handoverId)->first();
            if (! $handover || ! $handover->isPending()) {
                throw new \RuntimeException('This cash handover is no longer pending.');
            }
            if (! $handover->isMiddoTarget()) {
                throw new \RuntimeException('This handover is for kitchen, not Middo/ops.');
            }

            CashHandoverActions::reject($handover, $actorId, $this->rejectReason);
            $this->rejectReason = null;
            $this->statusMessage = "Due handover #{$handoverId} rejected. Rider can re-submit those orders.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reject cash handover.';
        }
    }

    public function render()
    {
        $filter = in_array($this->filter, ['pending', 'accepted', 'rejected', 'all'], true)
            ? $this->filter
            : 'pending';

        $query = CashHandover::query()
            ->with(['rider', 'items.order', 'acceptedBy'])
            ->where('target', CashHandover::TARGET_MIDDO)
            ->orderByDesc('id');

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        return view('livewire.operation.cash-handovers', [
            'handovers' => $query->paginate(20),
            'middoCashBalance' => MiddoCashLedger::balance(),
            'filter' => $filter,
        ])->layout('layouts.private.app', ['title' => 'Rider cash handovers']);
    }
}
