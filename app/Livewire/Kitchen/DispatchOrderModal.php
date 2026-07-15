<?php

namespace App\Livewire\Kitchen;

use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderMiddoBox;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class DispatchOrderModal extends Component
{
    public bool $showModal = false;

    public ?int $orderId = null;

    public string $orderLabel = '';

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
        $order = Order::with(['menuItem', 'orderGroup'])->find((int) $id);

        if (! $order || (int) $order->orderGroup?->kitchen_id !== (int) $kitchenId) {
            $this->errorMessage = 'Order not found for your kitchen.';
            $this->showModal = true;
            $this->orderLabel = 'Unavailable';
            $this->requiredQuantity = 0;
            $this->availableBoxes = [];
            $this->selectedBoxIds = [];

            return;
        }

        if (! in_array($order->order_status, ['pending', 'processing'], true) || $order->dispatched_at !== null) {
            $this->errorMessage = 'This order can no longer be dispatched.';
            $this->showModal = true;
            $this->orderId = $order->id;
            $this->orderLabel = '#'.$order->id;
            $this->requiredQuantity = (int) $order->quantity;
            $this->availableBoxes = [];
            $this->selectedBoxIds = [];

            return;
        }

        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->orderId = $order->id;
        $this->requiredQuantity = (int) $order->quantity;
        $this->orderLabel = '#'.$order->id.' · '.($order->menuItem?->name ?? 'Order').' · Qty '.$order->quantity;
        $this->selectedBoxIds = [];
        $this->availableBoxes = MiddoBox::query()
            ->atKitchen($kitchenId)
            ->whereDoesntHave('orderMiddoBoxes')
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

        $kitchenId = Auth::id();

        try {
            DB::transaction(function () use ($kitchenId) {
                $order = Order::with('orderGroup')
                    ->whereKey($this->orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order || $order->orderGroup?->kitchen_id !== $kitchenId) {
                    throw new \RuntimeException('Order not found for your kitchen.');
                }

                if (! in_array($order->order_status, ['pending', 'processing'], true)) {
                    throw new \RuntimeException('This order is no longer active.');
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
                    if (! $box->isAtKitchen($kitchenId)) {
                        throw new \RuntimeException("{$box->qr_code_id} is not in your kitchen inventory.");
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

                $order->update([
                    'order_status' => 'processing',
                    'dispatched_at' => now(),
                    'updated_by' => $kitchenId,
                ]);
            });

            $this->dispatch('order-dispatched');
            $this->closeModal();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not dispatch this order.';
        }
    }

    public function render()
    {
        return view('livewire.kitchen.dispatch-order-modal');
    }
}
