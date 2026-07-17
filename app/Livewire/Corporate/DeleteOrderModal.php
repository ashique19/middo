<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class DeleteOrderModal extends Component
{
    public bool $showModal = false;

    public ?int $orderId = null;

    public array $order = [];

    #[On('open-delete-order-modal')]
    public function openModal($orderId): void
    {
        $id = is_array($orderId) ? ($orderId['orderId'] ?? null) : $orderId;

        if (! $id) {
            return;
        }

        $order = $this->findPendingOrder((int) $id);

        if (! $order) {
            return;
        }

        $this->orderId = $order->id;
        $this->order = $order->load('menuItem')->toArray();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->orderId = null;
        $this->order = [];
    }

    public function confirmDelete()
    {
        $order = $this->findPendingOrder($this->orderId);

        if (! $order) {
            $this->closeModal();

            return;
        }

        DB::transaction(function () use ($order) {
            $user = Auth::user();
            $refund = (int) ($order->amount_paid ?? 0);
            if ($refund > 0) {
                $user->increment('balance', $refund);
            }
            $order->delete();
        });

        $this->closeModal();
        $this->dispatch('corporate-orders-changed');

        return redirect()->to(url()->previous());
    }

    protected function findPendingOrder(?int $orderId): ?Order
    {
        if (! $orderId) {
            return null;
        }

        return Order::with('menuItem')
            ->where('id', $orderId)
            ->where('user_id', Auth::id())
            ->where('order_status', 'pending')
            ->first();
    }

    public function render()
    {
        return view('livewire.corporate.delete-order-modal');
    }
}
