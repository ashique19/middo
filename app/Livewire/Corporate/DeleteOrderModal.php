<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use App\Models\WalletTransaction;
use App\Support\OrderCutoff;
use App\Support\WalletLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function confirmDelete()
    {
        $order = $this->findEditableOrder($this->orderId);

        if (! $order) {
            $this->errorMessage = OrderCutoff::modificationDeniedMessage();

            return;
        }

        DB::transaction(function () use ($order) {
            $user = Auth::user();
            $refund = (int) ($order->amount_paid ?? 0);
            if ($refund > 0) {
                WalletLedger::credit(
                    $user,
                    $refund,
                    WalletTransaction::TYPE_REFUND,
                    'Refund for cancelled order #'.$order->id,
                    $order
                );
            }
            $order->update([
                'order_status' => 'cancelled',
                'updated_by' => Auth::id(),
            ]);
        });

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
