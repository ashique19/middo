<?php

namespace App\Livewire\Delivery;

use App\Models\CashHandover;
use App\Models\CashHandoverOrder;
use App\Models\Order;
use App\Models\User;
use App\Support\RiderAccountLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CashHandovers extends Component
{
    use WithPagination;

    /** @var array<int> */
    public array $selectedOrderIds = [];

    public string $target = CashHandover::TARGET_KITCHEN;

    public ?string $notes = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function toggleOrder(int $orderId): void
    {
        if (in_array($orderId, $this->selectedOrderIds, true)) {
            $this->selectedOrderIds = array_values(array_filter(
                $this->selectedOrderIds,
                fn (int $id) => $id !== $orderId,
            ));
        } else {
            $this->selectedOrderIds[] = $orderId;
        }
    }

    public function createHandover(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $riderId = (int) Auth::id();

        if ($this->selectedOrderIds === []) {
            $this->errorMessage = 'Select at least one paid order to hand over.';

            return;
        }

        if (! in_array($this->target, [CashHandover::TARGET_KITCHEN, CashHandover::TARGET_MIDDO], true)) {
            $this->errorMessage = 'Choose kitchen or Middo as the handover target.';

            return;
        }

        try {
            $handoverId = DB::transaction(function () use ($riderId) {
                $orders = Order::query()
                    ->whereIn('id', $this->selectedOrderIds)
                    ->where('delivery_rider_id', $riderId)
                    ->where('order_status', 'delivered_and_paid')
                    ->where('payment_status', 'paid')
                    ->whereDoesntHave('cashHandoverOrder')
                    ->lockForUpdate()
                    ->get();

                if ($orders->count() !== count($this->selectedOrderIds)) {
                    throw new \RuntimeException('One or more selected orders are not available for cash handover.');
                }

                $amount = (int) $orders->sum(fn (Order $order) => $order->dueToMiddoAmount());
                if ($amount < 1) {
                    throw new \RuntimeException('Selected orders have no Due to Middo left to hand over.');
                }

                $rider = User::query()->whereKey($riderId)->lockForUpdate()->firstOrFail();

                if ((int) $rider->balance < $amount) {
                    throw new \RuntimeException('Your Due balance is lower than the selected Due total.');
                }

                $handover = CashHandover::create([
                    'rider_id' => $riderId,
                    'amount' => $amount,
                    'target' => $this->target,
                    'status' => 'pending',
                    'notes' => $this->notes,
                ]);

                foreach ($orders as $order) {
                    $due = $order->dueToMiddoAmount();
                    if ($due < 1) {
                        continue;
                    }
                    CashHandoverOrder::create([
                        'cash_handover_id' => $handover->id,
                        'order_id' => $order->id,
                        'amount' => $due,
                    ]);
                }

                return $handover->id;
            });

            $this->selectedOrderIds = [];
            $this->notes = null;
            $label = $this->target === CashHandover::TARGET_MIDDO ? 'Middo/ops' : 'kitchen';
            $this->statusMessage = "Due handover #{$handoverId} submitted for {$label} acceptance.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not create cash handover.';
        }
    }

    public function render()
    {
        $riderId = (int) Auth::id();

        $eligibleOrders = Order::query()
            ->with('menuItem')
            ->where('delivery_rider_id', $riderId)
            ->where('order_status', 'delivered_and_paid')
            ->where('payment_status', 'paid')
            ->whereDoesntHave('cashHandoverOrder')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (Order $order) => $order->dueToMiddoAmount() > 0)
            ->values();

        $handovers = CashHandover::query()
            ->with(['items.order.menuItem'])
            ->where('rider_id', $riderId)
            ->orderByDesc('id')
            ->paginate(10);

        $selectedTotal = $eligibleOrders
            ->whereIn('id', $this->selectedOrderIds)
            ->sum(fn (Order $order) => $order->dueToMiddoAmount());

        $dueBalance = (int) Auth::user()?->balance;
        $walletOwed = RiderAccountLedger::balance($riderId);

        return view('livewire.delivery.cash-handovers', [
            'eligibleOrders' => $eligibleOrders,
            'handovers' => $handovers,
            'selectedTotal' => (int) $selectedTotal,
            'dueBalance' => $dueBalance,
            'walletOwed' => $walletOwed,
        ])->layout('delivery.layout.app', ['title' => 'Cash handovers']);
    }
}
