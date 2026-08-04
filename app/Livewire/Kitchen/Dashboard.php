<?php

namespace App\Livewire\Kitchen;

use App\Models\OrderGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public array $tiles = [];

    public function mount(): void
    {
        $kitchenId = Auth::id();
        $today = now('Asia/Dhaka')->toDateString();
        $monthStart = Carbon::now('Asia/Dhaka')->startOfMonth()->toDateString();
        $monthEnd = Carbon::now('Asia/Dhaka')->endOfMonth()->toDateString();
        $threeMonthsStart = Carbon::now('Asia/Dhaka')->subMonths(2)->startOfMonth()->toDateString();

        $this->tiles = [
            [
                'label' => 'My Order this month',
                'count' => OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$monthStart, $monthEnd])
                    ->count(),
                'route' => 'kitchen.orders.this-month',
            ],
            [
                'label' => 'Last 3 months',
                'count' => OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$threeMonthsStart, $monthEnd])
                    ->count(),
                'route' => 'kitchen.orders.last-three-months',
            ],
            [
                'label' => 'Preparing',
                'count' => $this->activeOrdersQuery($kitchenId, $today)
                    ->whereHas('orders', fn ($q) => $q->where('order_status', 'processing'))
                    ->count(),
                'route' => 'kitchen.orders.active',
            ],
            [
                'label' => 'Ready for pickup',
                'count' => $this->activeOrdersQuery($kitchenId, $today)
                    ->whereHas('orders', fn ($q) => $q->where('order_status', 'ready'))
                    ->count(),
                'route' => 'kitchen.orders.active',
            ],
            [
                'label' => 'My active orders',
                'count' => $this->activeOrdersQuery($kitchenId, $today)->count(),
                'route' => 'kitchen.orders.active',
            ],
            [
                'label' => 'Middo order groups',
                'count' => $this->unassignedGroupsQuery($today)->count(),
                'route' => 'kitchen.order-groups.middo',
            ],
        ];
    }

    protected function activeOrdersQuery(int $kitchenId, string $today)
    {
        return OrderGroup::query()
            ->where('kitchen_id', $kitchenId)
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->active());
    }

    protected function unassignedGroupsQuery(string $today)
    {
        return OrderGroup::query()
            ->whereNull('kitchen_id')
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders');
    }

    public function render()
    {
        return view('livewire.kitchen.dashboard')
            ->layout('layouts.private.app', ['title' => 'Kitchen Dashboard']);
    }
}
