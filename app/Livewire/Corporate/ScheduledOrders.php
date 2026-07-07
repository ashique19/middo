<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ScheduledOrders extends Component
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
            ->where('delivery_date', '>=', now()->setTimezone('Asia/Dhaka')->toDateString())
            ->where('order_status', '!=', 'cancelled')
            ->orderBy('delivery_date', 'asc')
            ->orderBy('delivery_time', 'asc')
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.corporate.scheduled-orders')
            ->layout('layouts.public.app');
    }
}
