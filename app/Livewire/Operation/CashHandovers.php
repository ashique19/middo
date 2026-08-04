<?php

namespace App\Livewire\Operation;

use App\Models\CashHandover;
use App\Models\User;
use App\Support\MiddoCashLedger;
use App\Support\OrderMoneyFlow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CashHandovers extends Component
{
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

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

    public function render()
    {
        $handovers = CashHandover::query()
            ->with(['rider', 'items.order'])
            ->where('status', 'pending')
            ->where('target', CashHandover::TARGET_MIDDO)
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.operation.cash-handovers', [
            'handovers' => $handovers,
            'middoCashBalance' => MiddoCashLedger::balance(),
        ])->layout('layouts.private.app', ['title' => 'Rider cash handovers']);
    }
}
