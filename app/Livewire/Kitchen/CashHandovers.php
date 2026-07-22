<?php

namespace App\Livewire\Kitchen;

use App\Models\CashHandover;
use App\Models\User;
use App\Support\MiddoCashLedger;
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
        $kitchenId = (int) Auth::id();

        try {
            DB::transaction(function () use ($handoverId, $kitchenId) {
                $handover = CashHandover::query()
                    ->whereKey($handoverId)
                    ->lockForUpdate()
                    ->first();

                if (! $handover || ! $handover->isPending()) {
                    throw new \RuntimeException('This cash handover is no longer pending.');
                }

                $rider = User::query()->whereKey($handover->rider_id)->lockForUpdate()->firstOrFail();

                if ((int) $rider->balance < (int) $handover->amount) {
                    throw new \RuntimeException('Rider balance is insufficient for this handover.');
                }

                $rider->decrement('balance', (int) $handover->amount);

                MiddoCashLedger::credit(
                    (int) $handover->amount,
                    'cash_handover_accepted',
                    CashHandover::class,
                    $handover->id,
                    "Cash handover #{$handover->id} accepted from rider #{$rider->id}",
                    $kitchenId,
                );

                $handover->update([
                    'status' => 'accepted',
                    'accepted_by' => $kitchenId,
                    'accepted_at' => now(),
                ]);

                \App\Support\OrderMoneyFlow::recordCashHandover($handover->fresh(['items.order']));
            });

            $this->statusMessage = "Cash handover #{$handoverId} accepted. Middo cash ledger updated.";
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
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.kitchen.cash-handovers', [
            'handovers' => $handovers,
            'middoBalance' => MiddoCashLedger::balance(),
        ])->layout('layouts.private.app', ['title' => 'Cash handovers']);
    }
}
