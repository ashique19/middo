<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderHistory extends Component
{
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
            ->where('user_id', Auth::id())
            ->where('delivery_date', '<', now()->setTimezone('Asia/Dhaka')->toDateString())
            ->orderBy('delivery_date', 'desc')
            ->orderBy('delivery_time', 'desc')
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.corporate.order-history')
            ->layout('layouts.public.app');
    }
}
