<?php

namespace App\Livewire\Kitchen;

use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderMiddoBox;
use App\Support\OrderTransition;
use App\Support\StaffAlerts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class DispatchOrderModal extends Component
{
    public bool $showModal = false;

    public ?int $orderId = null;

    public string $orderLabel = '';

    public string $riderLabel = '';

    public int $requiredQuantity = 0;

    /** @var int[] */
    public array $selectedBoxIds = [];

    /** @var array<int, array{id: int, qr_code_id: string}> */
    public array $availableBoxes = [];

    public ?string $errorMessage = null;

    #[On('open-dispatch-order-modal')]
    public function openModal(mixed $orderId = null): void
    {
        $id = is_array($orderId) ? ($orderId['orderId'] ?? null) : $orderId;

        if (! $id) {
            return;
        }

        $kitchenId = Auth::id();
        $order = Order::with(['menuItem', 'orderGroup', 'deliveryRider', 'area'])->find((int) $id);

        if (! $order || (int) $order->orderGroup?->kitchen_id !== (int) $kitchenId) {
            $this->errorMessage = 'Order not found for your kitchen.';
            $this->showModal = true;
            $this->orderLabel = 'Unavailable';
            $this->riderLabel = '';
            $this->requiredQuantity = 0;
            $this->availableBoxes = [];
            $this->selectedBoxIds = [];

            return;
        }

        if (! $order->isRiderAssignedAwaitingDispatch()) {
            $this->errorMessage = match (true) {
                $order->order_status === OrderTransition::PROCESSING => 'Mark this order ready first.',
                $order->order_status === OrderTransition::READY => 'Wait for a rider to accept this order before dispatching.',
                $order->dispatched_at !== null => 'This order has already been dispatched.',
                default => 'This order can no longer be dispatched.',
            };
            $this->showModal = true;
            $this->orderId = $order->id;
            $this->orderLabel = '#'.$order->id;
            $this->riderLabel = $order->deliveryRider?->name ?? '';
            $this->requiredQuantity = (int) $order->quantity;
            $this->availableBoxes = [];
            $this->selectedBoxIds = [];

            return;
        }

        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->orderId = $order->id;
        $this->requiredQuantity = (int) $order->quantity;
        $riderName = $order->deliveryRider?->name ?: 'Rider #'.$order->delivery_rider_id;
        $area = $order->area?->name ?? '—';
        $this->riderLabel = $riderName;
        $this->orderLabel = '#'.$order->id.' · '.($order->menuItem?->name ?? 'Order').' · Qty '.$order->quantity.' · '.$area;
        $this->selectedBoxIds = [];
        $this->availableBoxes = MiddoBox::query()
            ->sendableAtKitchen($kitchenId)
            ->orderBy('qr_code_id')
            ->get()
            ->map(fn (MiddoBox $box) => [
                'id' => $box->id,
                'qr_code_id' => $box->qr_code_id,
            ])
            ->all();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->orderId = null;
        $this->orderLabel = '';
        $this->riderLabel = '';
        $this->requiredQuantity = 0;
        $this->selectedBoxIds = [];
        $this->availableBoxes = [];
        $this->errorMessage = null;
    }

    public function toggleBox(int $boxId): void
    {
        if (in_array($boxId, $this->selectedBoxIds, true)) {
            $this->selectedBoxIds = array_values(array_filter(
                $this->selectedBoxIds,
                fn (int $id) => $id !== $boxId
            ));

            return;
        }

        if (count($this->selectedBoxIds) >= $this->requiredQuantity) {
            $this->errorMessage = "Select exactly {$this->requiredQuantity} box(es) for this order.";

            return;
        }

        $this->errorMessage = null;
        $this->selectedBoxIds[] = $boxId;
    }

    public function dispatchOrder(): void
    {
        $this->errorMessage = null;

        if (! $this->orderId) {
            return;
        }

        if (count($this->selectedBoxIds) !== $this->requiredQuantity) {
            $this->errorMessage = "Select exactly {$this->requiredQuantity} box(es) for this order.";

            return;
        }

        $kitchenId = (int) Auth::id();

        try {
            DB::transaction(function () use ($kitchenId) {
                $order = Order::query()
                    ->whereKey($this->orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    throw new \RuntimeException('Order not found for your kitchen.');
                }

                $groupKitchenId = DB::table('order_group_orders')
                    ->join('order_groups', 'order_groups.id', '=', 'order_group_orders.order_group_id')
                    ->where('order_group_orders.order_id', $order->id)
                    ->value('order_groups.kitchen_id');

                if ($groupKitchenId === null || (int) $groupKitchenId !== $kitchenId) {
                    throw new \RuntimeException('Order not found for your kitchen.');
                }

                if (! $order->isRiderAssignedAwaitingDispatch()) {
                    throw new \RuntimeException(
                        $order->order_status === OrderTransition::READY
                            ? 'A rider must accept this order before you can dispatch.'
                            : 'This order is no longer ready to dispatch.'
                    );
                }

                if ($order->dispatched_at !== null) {
                    throw new \RuntimeException('This order has already been dispatched.');
                }

                $boxes = MiddoBox::query()
                    ->whereIn('id', $this->selectedBoxIds)
                    ->lockForUpdate()
                    ->get();

                if ($boxes->count() !== count($this->selectedBoxIds)) {
                    throw new \RuntimeException('One or more selected boxes are unavailable.');
                }

                foreach ($boxes as $box) {
                    if (! $box->isAtKitchen($kitchenId) || $box->isDamaged()) {
                        throw new \RuntimeException("{$box->qr_code_id} is not available in your kitchen inventory.");
                    }

                    if (OrderMiddoBox::query()->where('middo_box_id', $box->id)->exists()) {
                        throw new \RuntimeException("{$box->qr_code_id} is already reserved for another order.");
                    }

                    OrderMiddoBox::create([
                        'order_id' => $order->id,
                        'middo_box_id' => $box->id,
                    ]);

                    $box->update([
                        'last_scanned_at' => now(),
                    ]);
                }

                // Keep delivery_rider_id — kitchen is confirming the claimed rider.
                OrderTransition::apply($order, OrderTransition::PACKED, [
                    'dispatched_at' => now(),
                    'updated_by' => $kitchenId,
                ]);

                StaffAlerts::notifyRiderLunchPacked($order->fresh(['menuItem', 'orderGroup', 'deliveryRider']));
            });

            $this->dispatch('order-dispatched');
            $this->closeModal();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not pack this order.';
        }
    }

    public function render()
    {
        return view('livewire.kitchen.dispatch-order-modal');
    }
}
