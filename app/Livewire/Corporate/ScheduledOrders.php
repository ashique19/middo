<?php

namespace App\Livewire\Corporate;

use App\Livewire\Concerns\WithOrdersListView;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ScheduledOrders extends Component
{
    use WithOrdersListView;

    public array $orders = [];

    public function mount(): void
    {
        $this->loadOrders();
    }

    #[On('corporate-orders-changed')]
    public function refreshOrders(): void
    {
        $this->loadOrders();
    }

    protected function loadOrders(): void
    {
        $this->orders = Order::with('menuItem')
            ->withExists([
                'complaints as has_complaint' => fn ($q) => $q->whereNull('parent_id'),
            ])
            ->where('user_id', Auth::id())
            ->where('delivery_date', '>=', now()->setTimezone('Asia/Dhaka')->toDateString())
            ->where('order_status', '!=', 'cancelled')
            ->orderBy('delivery_date', 'asc')
            ->orderBy('delivery_time', 'asc')
            ->get()
            ->map(function (Order $order) {
                $row = $order->toArray();
                $party = $order->partyPayload();
                $row['payment_method'] = $party['payment_method'];
                $row['payment_method_label'] = $party['payment_method_label'];
                $row['has_complaint'] = (bool) ($order->has_complaint ?? false);

                return $row;
            })
            ->all();
    }

    public function render()
    {
        return view('livewire.corporate.scheduled-orders')
            ->layout('layouts.public.app');
    }
}
