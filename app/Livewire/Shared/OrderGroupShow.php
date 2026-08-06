<?php

namespace App\Livewire\Shared;

use App\Models\OrderGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class OrderGroupShow extends Component
{
    public OrderGroup $orderGroup;

    public function mount(OrderGroup $orderGroup): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
        $this->orderGroup = $orderGroup;
    }

    public function backRoute(): string
    {
        return Auth::user()?->role?->name === 'admin'
            ? route('admin.orders.active')
            : route('operation.orders.active');
    }

    public function kitchenShowRoute(): ?string
    {
        $kitchen = $this->orderGroup->kitchen;
        if (! $kitchen || $kitchen->role?->name !== 'kitchen') {
            return null;
        }

        $prefix = Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
        $name = $prefix.'.kitchens.show';

        return \Illuminate\Support\Facades\Route::has($name)
            ? route($name, $kitchen)
            : null;
    }

    public function menuShowRoute(): ?string
    {
        $menu = $this->orderGroup->menuItem;
        if (! $menu) {
            return null;
        }

        $prefix = Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
        $name = $prefix.'.menu.show';

        return \Illuminate\Support\Facades\Route::has($name)
            ? route($name, $menu)
            : null;
    }

    public function render()
    {
        $group = $this->orderGroup->fresh([
            'menuItem',
            'area.city',
            'kitchen.role',
            'orders' => fn ($q) => $q
                ->with(['menuItem', 'user', 'packageSubscription.package'])
                ->orderBy('delivery_time')
                ->orderBy('id'),
            'events' => fn ($q) => $q
                ->with(['createdBy', 'kitchen'])
                ->latest('id')
                ->limit(50),
        ]);

        $orders = $group->orders;
        $activeOrders = $orders->where('order_status', '!=', 'cancelled')->values();
        $totalQty = (int) $activeOrders->sum('quantity');

        return view('livewire.shared.order-groups.show', [
            'group' => $group,
            'orders' => $orders,
            'activeOrders' => $activeOrders,
            'totalQty' => $totalQty,
        ])->layout('layouts.private.app', [
            'title' => $group->name,
        ]);
    }
}
