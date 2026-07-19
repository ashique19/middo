<?php

namespace App\Livewire\Shared;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PackageDemand extends Component
{
    public string $deliveryDate = '';

    public function mount(): void
    {
        $this->deliveryDate = now('Asia/Dhaka')->addDay()->toDateString();
    }

    public function activeOrdersUrl(): string
    {
        return Auth::user()?->role?->name === 'admin'
            ? route('admin.orders.active')
            : route('operation.orders.active');
    }

    public function render()
    {
        $rows = Order::query()
            ->with('menuItem')
            ->whereDate('delivery_date', $this->deliveryDate)
            ->whereIn('order_status', Order::ACTIVE_STATUSES)
            ->select([
                'menu_item_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(CASE WHEN package_subscription_id IS NOT NULL THEN quantity ELSE 0 END) as package_qty'),
                DB::raw('SUM(CASE WHEN package_subscription_id IS NULL THEN quantity ELSE 0 END) as alacarte_qty'),
                DB::raw('COUNT(*) as order_count'),
            ])
            ->groupBy('menu_item_id')
            ->orderByDesc('total_qty')
            ->get();

        return view('livewire.shared.packages.demand', [
            'rows' => $rows,
        ])->layout('layouts.private.app', ['title' => 'Package Demand']);
    }
}
