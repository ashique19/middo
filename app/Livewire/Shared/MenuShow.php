<?php

namespace App\Livewire\Shared;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MenuShow extends Component
{
    public MenuItem $menuItem;

    public function mount(MenuItem $menuItem): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);

        $menuItem->load(['mealItems']);
        $this->menuItem = $menuItem;
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function indexRoute(): string
    {
        return route($this->rolePrefix().'.menu.index');
    }

    public function render()
    {
        $item = $this->menuItem;
        $kitchenCommissionPct = $item->price > 0
            ? round(($item->kitchen_commission / $item->price) * 100, 1)
            : 0;

        return view('livewire.shared.menu.show', [
            'item' => $item,
            'kitchenCommissionPct' => $kitchenCommissionPct,
        ])->layout('layouts.private.app', [
            'title' => $item->name.' · Menu',
        ]);
    }
}
