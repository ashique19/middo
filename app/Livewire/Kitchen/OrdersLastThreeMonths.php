<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\OrderGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class OrdersLastThreeMonths extends Component
{
    use FormatsOrderGroups;
    use WithPagination;

    public function render()
    {
        $kitchenId = Auth::id();
        $monthEnd = Carbon::now('Asia/Dhaka')->endOfMonth()->toDateString();
        $threeMonthsStart = Carbon::now('Asia/Dhaka')->subMonths(2)->startOfMonth()->toDateString();

        $groups = OrderGroup::with([
            'menuItem',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'user'])
                ->orderByDesc('delivery_date')
                ->orderBy('delivery_time'),
        ])
            ->where('kitchen_id', $kitchenId)
            ->whereBetween('delivery_date', [$threeMonthsStart, $monthEnd])
            ->orderByDesc('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $offset = ($groups->currentPage() - 1) * $groups->perPage();
        $groupNodes = $this->buildGroupNodes($groups->getCollection(), $offset);

        return view('livewire.kitchen.orders-last-three-months', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
            'rangeLabel' => Carbon::parse($threeMonthsStart, 'Asia/Dhaka')->format('M Y')
                .' – '
                .Carbon::now('Asia/Dhaka')->format('M Y'),
        ])->layout('layouts.private.app', ['title' => 'Orders — Last 3 Months']);
    }
}
