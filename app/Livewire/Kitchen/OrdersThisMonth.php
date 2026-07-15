<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\OrderGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class OrdersThisMonth extends Component
{
    use FormatsOrderGroups;
    use WithPagination;

    public function render()
    {
        $kitchenId = Auth::id();
        $monthStart = Carbon::now('Asia/Dhaka')->startOfMonth()->toDateString();
        $monthEnd = Carbon::now('Asia/Dhaka')->endOfMonth()->toDateString();

        $groups = OrderGroup::with([
            'menuItem',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'user'])
                ->orderByDesc('delivery_date')
                ->orderBy('delivery_time'),
        ])
            ->where('kitchen_id', $kitchenId)
            ->whereBetween('delivery_date', [$monthStart, $monthEnd])
            ->orderByDesc('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $offset = ($groups->currentPage() - 1) * $groups->perPage();
        $groupNodes = $this->buildGroupNodes($groups->getCollection(), $offset);

        return view('livewire.kitchen.orders-this-month', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
            'monthLabel' => Carbon::now('Asia/Dhaka')->format('F Y'),
        ])->layout('layouts.private.app', ['title' => 'My Orders This Month']);
    }
}
