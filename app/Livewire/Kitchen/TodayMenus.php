<?php

namespace App\Livewire\Kitchen;

use App\Models\Order;
use App\Models\OrderGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TodayMenus extends Component
{
    public string $deliveryDate = '';

    public function mount(): void
    {
        $this->deliveryDate = now('Asia/Dhaka')->toDateString();
    }

    public function render()
    {
        $kitchenId = Auth::id();

        $menus = Order::query()
            ->with('menuItem')
            ->whereDate('delivery_date', $this->deliveryDate)
            ->whereIn('order_status', Order::ACTIVE_STATUSES)
            ->where(function ($query) use ($kitchenId) {
                $query->whereHas('orderGroup', function ($groupQuery) use ($kitchenId) {
                    $groupQuery->where('kitchen_id', $kitchenId)
                        ->orWhereNull('kitchen_id');
                })->orWhereDoesntHave('orderGroup');
            })
            ->select([
                'menu_item_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(CASE WHEN package_subscription_id IS NOT NULL THEN quantity ELSE 0 END) as package_qty'),
                DB::raw('COUNT(*) as order_count'),
            ])
            ->groupBy('menu_item_id')
            ->orderByDesc('total_qty')
            ->get();

        $assignedGroupCount = OrderGroup::query()
            ->where('kitchen_id', $kitchenId)
            ->whereDate('delivery_date', $this->deliveryDate)
            ->count();

        return view('livewire.kitchen.today-menus', [
            'menus' => $menus,
            'assignedGroupCount' => $assignedGroupCount,
        ])->layout('kitchen.layout.app', ['title' => "Today's Menus"]);
    }
}
