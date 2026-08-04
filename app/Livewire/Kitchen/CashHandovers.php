<?php

namespace App\Livewire\Kitchen;

use App\Models\CashHandover;
use App\Models\User;
use App\Support\KitchenAccountLedger;
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
        $kitchenId = (int) Auth::id();

        try {
            DB::transaction(function () use ($handoverId, $kitchenId) {
                $handover = CashHandover::query()
                    ->with('items.order.orderGroup')
                    ->whereKey($handoverId)
                    ->lockForUpdate()
                    ->first();

                if (! $handover || ! $handover->isPending()) {
                    throw new \RuntimeException('This cash handover is no longer pending.');
                }

                if (! $handover->isKitchenTarget()) {
                    throw new \RuntimeException('This handover is for Middo/ops, not kitchen.');
                }

                if (! $this->handoverBelongsToKitchen($handover, $kitchenId)) {
                    throw new \RuntimeException('This cash handover is not linked to your kitchen’s orders.');
                }

                $rider = User::query()->whereKey($handover->rider_id)->lockForUpdate()->firstOrFail();

                if ((int) $rider->balance < (int) $handover->amount) {
                    throw new \RuntimeException('Rider balance is insufficient for this handover.');
                }

                $rider->decrement('balance', (int) $handover->amount);

                // Kitchen received cash: debit wallet (Middo owes less / kitchen may owe Middo).
                KitchenAccountLedger::debit(
                    $kitchenId,
                    (int) $handover->amount,
                    'cash_received',
                    CashHandover::class,
                    $handover->id,
                    "Cash handover #{$handover->id} from rider #{$rider->id}",
                    $kitchenId,
                );

                $handover->update([
                    'status' => 'accepted',
                    'accepted_by' => $kitchenId,
                    'accepted_at' => now(),
                ]);

                OrderMoneyFlow::recordCashHandover($handover->fresh(['items.order.orderGroup']), $kitchenId);
            });

            $balance = KitchenAccountLedger::balance($kitchenId);
            $this->statusMessage = $balance < 0
                ? "Cash handover #{$handoverId} accepted. You now owe Middo ৳".number_format(abs($balance)).'.'
                : "Cash handover #{$handoverId} accepted. Kitchen wallet balance ৳".number_format($balance).'.';
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept cash handover.';
        }
    }

    protected function handoverBelongsToKitchen(CashHandover $handover, int $kitchenId): bool
    {
        $handover->loadMissing('items.order.orderGroup');

        if ($handover->items->isEmpty()) {
            return false;
        }

        return $handover->items->every(function ($item) use ($kitchenId) {
            return (int) ($item->order?->orderGroup?->kitchen_id) === $kitchenId;
        });
    }

    public function render()
    {
        $kitchenId = (int) Auth::id();

        $scopedIds = CashHandover::query()
            ->with(['items.order.orderGroup'])
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('target', CashHandover::TARGET_KITCHEN)
                    ->orWhereNull('target');
            })
            ->get()
            ->filter(fn (CashHandover $h) => $this->handoverBelongsToKitchen($h, $kitchenId))
            ->pluck('id')
            ->all();

        $handovers = CashHandover::query()
            ->with(['rider', 'items.order'])
            ->whereIn('id', $scopedIds ?: [0])
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.kitchen.cash-handovers', [
            'handovers' => $handovers,
            'walletBalance' => KitchenAccountLedger::balance($kitchenId),
        ])->layout('kitchen.layout.app', ['title' => 'Cash handovers']);
    }
}
