<?php

namespace App\Livewire\Kitchen;

use App\Models\OrderGroup;
use App\Support\KitchenBoxStock;
use App\Support\StaffAlerts;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public array $tiles = [];

    public bool $insufficientBoxStock = false;

    /** @var list<array{count: int, name: string, mobile: ?string, label: string}> */
    public array $opsIncomingNotices = [];

    public function mount(): void
    {
        $kitchen = Auth::user();
        $kitchenId = Auth::id();
        $today = now('Asia/Dhaka')->toDateString();
        $monthStart = Carbon::now('Asia/Dhaka')->startOfMonth()->toDateString();
        $monthEnd = Carbon::now('Asia/Dhaka')->endOfMonth()->toDateString();
        $lastMonthStart = Carbon::now('Asia/Dhaka')->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = Carbon::now('Asia/Dhaka')->subMonthNoOverflow()->endOfMonth()->toDateString();
        $threeMonthsStart = Carbon::now('Asia/Dhaka')->subMonths(2)->startOfMonth()->toDateString();

        if ($kitchen) {
            $this->insufficientBoxStock = KitchenBoxStock::hasInsufficientStockVsAllowed($kitchen);
        }

        $this->opsIncomingNotices = KitchenBoxStock::opsIncomingNotices((int) $kitchenId);

        $this->tiles = [
            [
                'label' => 'Alerts',
                'count' => StaffAlerts::unreadCount((int) $kitchenId),
                'route' => 'kitchen.alerts',
            ],
            [
                'label' => 'My active orders',
                'count' => $this->activeOrdersQuery($kitchenId, $today)->count(),
                'route' => 'kitchen.orders.active',
            ],
            [
                'label' => 'Preparing',
                'count' => $this->activeOrdersQuery($kitchenId, $today)
                    ->whereHas('orders', fn ($q) => $q->where('order_status', 'processing'))
                    ->count(),
                'route' => 'kitchen.orders.active',
            ],
            [
                'label' => 'Ready for pickup',
                'count' => $this->activeOrdersQuery($kitchenId, $today)
                    ->whereHas('orders', fn ($q) => $q->where('order_status', 'ready'))
                    ->count(),
                'route' => 'kitchen.orders.active',
            ],
            [
                'label' => 'Middo order groups',
                'count' => $this->unassignedGroupsQuery($today)->count(),
                'route' => 'kitchen.order-groups.middo',
            ],
            [
                'label' => 'Boxes in Stock',
                'count' => KitchenBoxStock::sendableCount((int) $kitchenId),
                'route' => 'kitchen.middo-boxes.at-kitchen',
            ],
            [
                'label' => 'My Orders this month',
                'count' => OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$monthStart, $monthEnd])
                    ->count(),
                'route' => 'kitchen.orders.this-month',
            ],
            [
                'label' => 'Last month',
                'count' => OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$lastMonthStart, $lastMonthEnd])
                    ->count(),
                'route' => 'kitchen.orders.last-month',
            ],
            [
                'label' => 'Last 3 months',
                'count' => OrderGroup::query()
                    ->where('kitchen_id', $kitchenId)
                    ->whereBetween('delivery_date', [$threeMonthsStart, $monthEnd])
                    ->count(),
                'route' => 'kitchen.orders.last-three-months',
            ],
        ];
    }

    protected function activeOrdersQuery(int $kitchenId, string $today)
    {
        return OrderGroup::query()
            ->where('kitchen_id', $kitchenId)
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->active());
    }

    protected function unassignedGroupsQuery(string $today)
    {
        return OrderGroup::query()
            ->whereNull('kitchen_id')
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders');
    }

    public function render()
    {
        return view('livewire.kitchen.dashboard')
            ->layout('kitchen.layout.app', ['title' => 'Kitchen Dashboard']);
    }
}
