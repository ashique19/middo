<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use App\Support\OrderCancellation;
use App\Support\OrderCutoff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class DeleteOrderModal extends Component
{
    public bool $showModal = false;

    public ?int $orderId = null;

    public array $order = [];

    public string $errorMessage = '';

    #[On('open-delete-order-modal')]
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

        $this->errorMessage = '';
        $this->orderId = $order->id;
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

    public function getRefundableAmountProperty(): int
    {
        if (empty($this->order)) {
            return 0;
        }

        $amountPaid = (int) ($this->order['amount_paid'] ?? 0);
        if ($amountPaid > 0) {
            return $amountPaid;
        }

        return (int) ($this->order['prepaid_amount'] ?? 0);
    }

    public function getIsPrepaidProperty(): bool
    {
        return $this->refundableAmount > 0;
    }

    public function confirmDelete()
    {
        if (! $this->orderId || ! Auth::user()) {
            $this->errorMessage = OrderCutoff::modificationDeniedMessage();

            return;
        }

        try {
            OrderCancellation::cancelPendingOwnedBy(Auth::user(), (int) $this->orderId);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first()
                ?: OrderCutoff::modificationDeniedMessage();

            return;
        }

        $this->closeModal();
        $this->dispatch('corporate-orders-changed');

        return redirect()->to(url()->previous());
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
        return view('livewire.corporate.delete-order-modal');
    }
}
