<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use App\Support\CorporateOrderLimit;
use App\Support\OrderCutoff;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class EditOrderModal extends Component
{
    public bool $showModal = false;

    public ?int $orderId = null;

    public int $quantity = 1;

    public array $order = [];

    public string $errorMessage = '';

    #[On('open-edit-order-modal')]
    public function openModal($orderId): void
    {
        $id = is_array($orderId) ? ($orderId['orderId'] ?? null) : $orderId;

        if (! $id) {
            return;
        }

        $order = $this->findEditableOrder((int) $id);

        if (! $order) {
            return;
        }

        $this->resetErrorBag();
        $this->errorMessage = '';
        $this->orderId = $order->id;
        $this->quantity = $order->quantity;
        $this->order = $order->load('menuItem')->toArray();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->orderId = null;
        $this->order = [];
        $this->errorMessage = '';
    }

    public function decrementQuantity(): void
    {
        if ($this->quantity > 1) {
            $this->quantity--;
        }
    }

    public function incrementQuantity(): void
    {
        if ($this->quantity < $this->maxQuantity) {
            $this->quantity++;
        }
    }

    public function getMaxQuantityProperty(): int
    {
        if (empty($this->order['delivery_date'])) {
            return CorporateOrderLimit::maxAllowed();
        }

        return max(1, CorporateOrderLimit::remainingQtyForDate(
            Auth::id(),
            $this->order['delivery_date'],
            $this->orderId
        ));
    }

    public function save(): void
    {
        $maxQty = $this->maxQuantity;

        $this->validate([
            'quantity' => "required|integer|min:1|max:{$maxQty}",
        ], [
            'quantity.max' => $this->dailyLimitMessage(),
        ]);

        $order = $this->findEditableOrder($this->orderId);

        if (! $order) {
            $this->errorMessage = OrderCutoff::modificationDeniedMessage();

            return;
        }

        $order->update([
            'quantity' => $this->quantity,
            'total_amount' => $order->menuItem->price * $this->quantity,
            'updated_by' => Auth::id(),
        ]);

        $this->closeModal();
        $this->dispatch('corporate-orders-changed');
    }

    protected function dailyLimitMessage(): string
    {
        $formattedDate = \Carbon\Carbon::parse($this->order['delivery_date'])->format('M d, Y');
        $max = CorporateOrderLimit::maxAllowed();

        return "Maximum {$max} meals allowed per day on {$formattedDate}. You can set up to {$this->maxQuantity} for this order.";
    }

    protected function findEditableOrder(?int $orderId): ?Order
    {
        if (! $orderId) {
            return null;
        }

        $order = Order::with('menuItem')
            ->where('id', $orderId)
            ->where('user_id', Auth::id())
            ->where('order_status', 'pending')
            ->first();

        if (! $order || ! OrderCutoff::allowsModification($order)) {
            return null;
        }

        return $order;
    }

    public function render()
    {
        return view('livewire.corporate.edit-order-modal');
    }
}
