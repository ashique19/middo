<?php

namespace App\Livewire\Kitchen;

use App\Models\Order;
use App\Support\OrderKitchenDispatch;
use App\Support\OrderTransition;
use Illuminate\Support\Facades\Auth;
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
                $order->order_status === OrderTransition::READY => 'Wait for ops to assign a rider before dispatching.',
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
        $this->availableBoxes = OrderKitchenDispatch::availableBoxesForKitchen((int) $kitchenId);
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
            $order = Order::query()->find($this->orderId);
            if (! $order) {
                throw new \RuntimeException('Order not found for your kitchen.');
            }

            OrderKitchenDispatch::dispatchWithBoxes($order, $kitchenId, $this->selectedBoxIds);

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
