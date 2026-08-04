<?php

namespace App\Livewire\Delivery;

use App\Models\MiddoBox;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public array $tiles = [];

    public function mount(): void
    {
        $riderId = Auth::id();

        $this->tiles = [
            [
                'label' => 'Kitchen dispatches',
                'count' => Order::query()->kitchenDispatched()->count(),
                'route' => 'delivery.kitchen-dispatches',
            ],
            [
                'label' => 'Middo boxes pending run',
                'count' => MiddoBox::query()
                    ->where('held_by_user_id', $riderId)
                    ->where('asset_status', 'active')
                    ->count(),
                'route' => 'delivery.middo-boxes.pending-run',
            ],
            [
                'label' => 'Delivered orders',
                'count' => Order::query()->deliveredForRider($riderId)->count(),
                'route' => 'delivery.orders.delivered',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.delivery.dashboard')
            ->layout('delivery.layout.app', ['title' => 'Delivery Dashboard']);
    }
}
