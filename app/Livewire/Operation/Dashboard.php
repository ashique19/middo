<?php

namespace App\Livewire\Operation;

use App\Models\MealItem;
use App\Models\MiddoBox;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Livewire\Component;

class Dashboard extends Component
{
    public array $tiles = [];

    public function mount(): void
    {
        $kitchenRoleId = Role::where('name', 'kitchen')->value('id');

        $this->tiles = [
            [
                'label' => 'Active Orders',
                'count' => Order::future()->active()->count(),
                'route' => 'operation.orders.active',
            ],
            [
                'label' => 'Kitchen',
                'count' => $kitchenRoleId
                    ? User::where('role_id', $kitchenRoleId)->where('status', 'active')->count()
                    : 0,
                'route' => 'operation.kitchens.index',
            ],
            [
                'label' => 'Menu',
                'count' => MenuItem::count(),
                'route' => 'operation.menu.index',
            ],
            [
                'label' => 'Meal Items',
                'count' => MealItem::count(),
                'route' => 'operation.meal-items.index',
            ],
            [
                'label' => 'Middo Boxes',
                'count' => MiddoBox::count(),
                'route' => 'operation.middo-boxes.index',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.operation.dashboard')
            ->layout('layouts.private.app', ['title' => 'Operation Dashboard']);
    }
}
