<?php

namespace App\Livewire\Corporate;

use App\Livewire\Concerns\WithOrdersListView;
use App\Models\Order;
use App\Support\CorporateOrderPresentation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderHistory extends Component
{
    use WithOrdersListView;

    public array $orders = [];

    public bool $hasEverOrdered = false;

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
        $userId = (int) Auth::id();

        $this->hasEverOrdered = Order::query()
            ->where('user_id', $userId)
            ->where('order_status', '!=', 'cancelled')
            ->exists();

        $this->orders = Order::with('menuItem')
            ->withExists([
                'complaints as has_complaint' => fn ($q) => $q->whereNull('parent_id'),
            ])
            ->where('user_id', $userId)
            ->where('delivery_date', '<', now()->setTimezone('Asia/Dhaka')->toDateString())
            ->orderBy('delivery_date', 'desc')
            ->orderBy('delivery_time', 'desc')
            ->get()
            ->map(fn (Order $order) => CorporateOrderPresentation::present($order))
            ->all();
    }

    public function render()
    {
        return view('livewire.corporate.order-history')
            ->layout('layouts.public.app');
    }
}
