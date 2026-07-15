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
        ];
    }

    public function render()
    {
        return view('livewire.delivery.dashboard')
            ->layout('layouts.private.app', ['title' => 'Delivery Dashboard']);
    }
}
