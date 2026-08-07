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

class OrdersThisMonth extends Component
{
    use FormatsOrderGroups;
    use WithOrdersListView;
    use WithPagination;

    public function exportExcel(): StreamedResponse
    {
        $kitchenId = Auth::id();
        $monthStart = Carbon::now('Asia/Dhaka')->startOfMonth()->toDateString();
        $monthEnd = Carbon::now('Asia/Dhaka')->endOfMonth()->toDateString();

        $orders = Order::query()
            ->with(['menuItem', 'area', 'orderGroup.area'])
            ->whereBetween('delivery_date', [$monthStart, $monthEnd])
            ->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $kitchenId))
            ->orderByDesc('delivery_date')
            ->orderBy('delivery_time')
            ->get();

        return OrdersExcelExport::download($orders, 'kitchen-orders-this-month-'.now('Asia/Dhaka')->format('Y-m').'.csv', kitchenSafe: true);
    }

    public function render()
    {
        $kitchenId = Auth::id();
        $monthStart = Carbon::now('Asia/Dhaka')->startOfMonth()->toDateString();
        $monthEnd = Carbon::now('Asia/Dhaka')->endOfMonth()->toDateString();

        $groups = OrderGroup::with([
            'menuItem',
            'area',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'area'])
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
        $flatOrders = collect($groupNodes)
            ->flatMap(fn (array $group) => collect($group['orders'])->map(
                fn (array $order) => array_merge($order, ['group_name' => $group['name']])
            ))
            ->values()
            ->all();

        return view('livewire.kitchen.orders-this-month', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
            'flatOrders' => $flatOrders,
            'monthLabel' => Carbon::now('Asia/Dhaka')->format('F Y'),
        ])->layout('kitchen.layout.app', ['title' => 'My Orders This Month']);
    }
}
