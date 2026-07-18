<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Concerns\WithOrdersListView;
use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Support\OrdersExcelExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdersLastThreeMonths extends Component
{
    use FormatsOrderGroups;
    use WithOrdersListView;
    use WithPagination;

    public function exportExcel(): StreamedResponse
    {
        $kitchenId = Auth::id();
        $monthEnd = Carbon::now('Asia/Dhaka')->endOfMonth()->toDateString();
        $threeMonthsStart = Carbon::now('Asia/Dhaka')->subMonths(2)->startOfMonth()->toDateString();

        $orders = Order::query()
            ->with(['menuItem', 'user', 'orderGroup'])
            ->whereBetween('delivery_date', [$threeMonthsStart, $monthEnd])
            ->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $kitchenId))
            ->orderByDesc('delivery_date')
            ->orderBy('delivery_time')
            ->get();

        return OrdersExcelExport::download($orders, 'kitchen-orders-last-3-months-'.now('Asia/Dhaka')->format('Y-m-d').'.csv');
    }

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
        $flatOrders = collect($groupNodes)
            ->flatMap(fn (array $group) => collect($group['orders'])->map(
                fn (array $order) => array_merge($order, ['group_name' => $group['name']])
            ))
            ->values()
            ->all();

        return view('livewire.kitchen.orders-last-three-months', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
            'flatOrders' => $flatOrders,
            'rangeLabel' => Carbon::parse($threeMonthsStart, 'Asia/Dhaka')->format('M Y')
                .' – '
                .Carbon::now('Asia/Dhaka')->format('M Y'),
        ])->layout('layouts.private.app', ['title' => 'Orders — Last 3 Months']);
    }
}
