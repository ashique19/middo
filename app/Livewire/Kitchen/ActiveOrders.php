<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\OrderGroup;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ActiveOrders extends Component
{
    use FormatsOrderGroups;
    use WithPagination;

    public function render()
    {
        $kitchenId = Auth::id();
        $today = now('Asia/Dhaka')->toDateString();

        $groups = OrderGroup::with([
            'menuItem',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'user'])
                ->active()
                ->orderBy('delivery_time'),
        ])
            ->where('kitchen_id', $kitchenId)
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->active())
            ->orderBy('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $offset = ($groups->currentPage() - 1) * $groups->perPage();
        $groupNodes = $this->buildGroupNodes($groups->getCollection(), $offset);

        return view('livewire.kitchen.active-orders', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
        ])->layout('layouts.private.app', ['title' => 'My Active Orders']);
    }
}
