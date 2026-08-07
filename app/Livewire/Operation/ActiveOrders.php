<?php

namespace App\Livewire\Operation;

use App\Livewire\Concerns\WithOrdersListView;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use App\Support\KitchenCapacity;
use App\Support\MealOrderGrouper;
use App\Support\OpsSlaBoard;
use App\Support\OrderGroupManager;
use App\Support\OrderKitchenAcceptance;
use App\Support\OrdersExcelExport;
use App\Support\PackageOrderPresenter;
use App\Support\StaffAlerts;
use App\Support\StaffPortal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActiveOrders extends Component
{
    use WithOrdersListView;

    public array $dateSections = [];

    /** @var string[] Expanded date accordion keys */
    public array $expandedDates = [];

    public ?string $statusMessage = null;

    /** all|package|alacarte */
    public string $packageFilter = 'all';

    /** all|pending|processing|ready|packed|on_the_way_to_delivery */
    public string $statusFilter = 'all';

    /** all|unassigned|{kitchen user id} */
    public string $kitchenFilter = 'all';

    public bool $awaitingRiderOnly = false;

    public bool $lateOnly = false;

    public ?int $bulkKitchenId = null;

    public function mount(): void
    {
        $this->loadOrders();
    }

    public function updatedPackageFilter(): void
    {
        if (! in_array($this->packageFilter, ['all', 'package', 'alacarte'], true)) {
            $this->packageFilter = 'all';
        }

        $this->loadOrders();
    }

    public function updatedStatusFilter(): void
    {
        $allowed = ['all', 'pending', 'processing', 'ready', 'rider_assigned', 'packed', 'on_the_way_to_delivery'];
        if (! in_array($this->statusFilter, $allowed, true)) {
            $this->statusFilter = 'all';
        }

        $this->loadOrders();
    }

    public function updatedKitchenFilter(): void
    {
        $this->loadOrders();
    }

    public function updatedAwaitingRiderOnly(): void
    {
        $this->loadOrders();
    }

    public function updatedLateOnly(): void
    {
        $this->loadOrders();
    }

    #[On('order-group-kitchen-changed')]
    public function refreshOrders(): void
    {
        $this->loadOrders();
    }

    public function toggleDate(string $deliveryDate): void
    {
        if (in_array($deliveryDate, $this->expandedDates, true)) {
            $this->expandedDates = array_values(array_filter(
                $this->expandedDates,
                fn (string $date) => $date !== $deliveryDate
            ));
        } else {
            $this->expandedDates[] = $deliveryDate;
        }
    }

    public function autoGroupForDate(string $deliveryDate): void
    {
        if (! StaffPortal::isDayOps()) {
            $this->statusMessage = 'Only admin/operation can regroup active orders.';

            return;
        }

        $userId = Auth::id();

        if (! $userId) {
            $this->statusMessage = 'You must be logged in to group orders.';

            return;
        }

        $orders = Order::with(['menuItem', 'user', 'orderGroup'])
            ->future()
            ->active()
            ->whereDate('delivery_date', $deliveryDate)
            ->orderBy('delivery_time')
            ->get();

        $assigned = app(MealOrderGrouper::class)->autoGroup($orders, $userId);

        if (! in_array($deliveryDate, $this->expandedDates, true)) {
            $this->expandedDates[] = $deliveryDate;
        }

        $this->loadOrders();

        $this->statusMessage = $assigned > 0
            ? "{$assigned} order(s) auto-grouped for {$this->dateLabel($deliveryDate)}."
            : "No new orders available to auto-group for {$this->dateLabel($deliveryDate)}.";
    }

    public function ungroupOrder(int $orderId): void
    {
        if (! StaffPortal::isDayOps()) {
            $this->statusMessage = 'Only admin/operation can regroup active orders.';

            return;
        }

        try {
            app(OrderGroupManager::class)->ungroup($orderId);
            $this->loadOrders();
            $this->statusMessage = 'Order removed from group.';
        } catch (\Throwable $e) {
            $this->statusMessage = 'Could not ungroup order.';
        }
    }

    public function handleOrderDrop(int $sourceOrderId, string $targetType, ?int $targetId = null): void
    {
        if (! StaffPortal::isDayOps()) {
            $this->statusMessage = 'Only admin/operation can regroup active orders.';

            return;
        }

        if ($sourceOrderId <= 0) {
            return;
        }

        try {
            app(OrderGroupManager::class)->handleDrop(
                $sourceOrderId,
                $targetType,
                $targetId,
                Auth::id()
            );

            $this->loadOrders();
            $this->statusMessage = null;
        } catch (\Throwable $e) {
            $this->statusMessage = 'Could not move order: '.$e->getMessage();
        }
    }

    /**
     * Assign all currently visible unassigned groups to the selected kitchen.
     */
    public function bulkAssignUnassignedKitchen(): void
    {
        if (! StaffPortal::isDayOps()) {
            $this->statusMessage = 'Only admin/operation can assign kitchens.';

            return;
        }

        if (! $this->bulkKitchenId) {
            $this->statusMessage = 'Select a kitchen first.';

            return;
        }

        $kitchen = User::query()
            ->whereKey($this->bulkKitchenId)
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'active')
            ->first();

        if (! $kitchen) {
            $this->statusMessage = 'Kitchen not found.';

            return;
        }

        $groupIds = collect($this->dateSections)
            ->flatMap(fn (array $section) => $section['groups'])
            ->filter(fn (array $group) => empty($group['kitchen_id']))
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        if ($groupIds === []) {
            $this->statusMessage = 'No unassigned groups in the current filter.';

            return;
        }

        $assigned = 0;
        $skipped = 0;

        foreach ($groupIds as $groupId) {
            $group = OrderGroup::with('orders')->find($groupId);
            if (! $group || $group->kitchen_id !== null) {
                $skipped++;

                continue;
            }

            try {
                KitchenCapacity::assertCanAccept($kitchen);
            } catch (\RuntimeException $e) {
                $this->statusMessage = "Assigned {$assigned} group(s). Stopped: ".$e->getMessage();
                $this->loadOrders();

                return;
            }

            $group->update([
                'kitchen_id' => $kitchen->id,
                'updated_by' => Auth::id(),
            ]);

            OrderKitchenAcceptance::markGroupOrdersProcessing($group, Auth::id());
            StaffAlerts::notifyKitchenAssigned($group->fresh(['menuItem']), $kitchen);
            $assigned++;
        }

        $this->bulkKitchenId = null;
        $this->loadOrders();
        $this->statusMessage = $assigned > 0
            ? "Assigned {$assigned} unassigned group(s) to {$kitchen->name}."
                .($skipped > 0 ? " Skipped {$skipped}." : '')
            : 'No groups were assigned.';
    }

    protected function loadOrders(): void
    {
        $lateIds = $this->lateOnly
            ? OpsSlaBoard::lateToPack()->pluck('id')->all()
            : null;

        $orders = $this->baseOrderQuery()
            ->with(['menuItem', 'user', 'orderGroup.kitchen', 'packageSubscription.package'])
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->get()
            ->when($lateIds !== null, fn (Collection $c) => $c->filter(
                fn (Order $order) => in_array($order->id, $lateIds, true)
            )->values());

        $this->dateSections = $orders
            ->groupBy(fn (Order $order) => $order->delivery_date->toDateString())
            ->map(function (Collection $dayOrders, string $date) {
                return $this->buildDateTree($date, $dayOrders);
            })
            ->values()
            ->all();

        if ($this->expandedDates === [] && $this->dateSections !== []) {
            $this->expandedDates = [$this->dateSections[0]['date']];
        }
    }

    protected function baseOrderQuery()
    {
        return Order::query()
            ->future()
            ->active()
            ->when($this->packageFilter === 'package', fn ($q) => $q->whereNotNull('package_subscription_id'))
            ->when($this->packageFilter === 'alacarte', fn ($q) => $q->whereNull('package_subscription_id'))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('order_status', $this->statusFilter))
            ->when($this->awaitingRiderOnly, function ($q) {
                $q->where('order_status', 'packed')
                    ->whereNotNull('dispatched_at')
                    ->whereNull('delivery_rider_id');
            })
            ->when($this->kitchenFilter === 'unassigned', function ($q) {
                $q->where(function ($inner) {
                    $inner->whereDoesntHave('orderGroup')
                        ->orWhereHas('orderGroup', fn ($g) => $g->whereNull('kitchen_id'));
                });
            })
            ->when(
                $this->kitchenFilter !== 'all' && $this->kitchenFilter !== 'unassigned',
                function ($q) {
                    $kitchenId = (int) $this->kitchenFilter;
                    $q->whereHas('orderGroup', fn ($g) => $g->where('kitchen_id', $kitchenId));
                }
            );
    }

    protected function buildDateTree(string $date, Collection $dayOrders): array
    {
        $colorPalette = [
            'bg-amber-50/80 border-amber-200',
            'bg-sky-50/80 border-sky-200',
            'bg-emerald-50/80 border-emerald-200',
            'bg-violet-50/80 border-violet-200',
            'bg-rose-50/80 border-rose-200',
            'bg-teal-50/80 border-teal-200',
            'bg-orange-50/80 border-orange-200',
            'bg-indigo-50/80 border-indigo-200',
        ];

        $dayOrderIds = $dayOrders->pluck('id')->all();

        $groups = OrderGroup::with([
            'menuItem',
            'kitchen',
            'orders' => fn ($q) => $q->with(['menuItem', 'user', 'packageSubscription.package']),
        ])
            ->whereDate('delivery_date', $date)
            ->orderBy('name')
            ->get()
            ->values()
            ->map(function (OrderGroup $group, int $index) use ($colorPalette, $dayOrderIds) {
                $orders = $group->orders
                    ->filter(fn (Order $order) => in_array($order->id, $dayOrderIds, true))
                    ->sortBy('delivery_time')
                    ->values();

                $orderNodes = $orders
                    ->map(fn (Order $order) => $this->formatOrderNode($order, false))
                    ->all();

                if ($orderNodes === []) {
                    return null;
                }

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'menu_name' => $group->menuItem?->name ?? 'Unknown',
                    'kitchen_id' => $group->kitchen_id,
                    'kitchen_label' => $group->kitchenDisplayName(),
                    'total_quantity' => $orders->sum('quantity'),
                    'color' => $colorPalette[$index % count($colorPalette)],
                    'package_source' => PackageOrderPresenter::groupSource($orderNodes),
                    'has_package_orders' => PackageOrderPresenter::collectionHasPackage($orderNodes),
                    'orders' => $orderNodes,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $groupedOrderIds = collect($groups)
            ->flatMap(fn (array $group) => collect($group['orders'])->pluck('id'));

        $ungrouped = $dayOrders
            ->reject(fn (Order $order) => $groupedOrderIds->contains($order->id))
            ->values()
            ->map(fn (Order $order) => $this->formatOrderNode($order, true))
            ->all();

        return [
            'date' => $date,
            'label' => $this->dateLabel($date),
            'count' => $dayOrders->count(),
            'total_quantity' => $dayOrders->sum('quantity'),
            'has_package_orders' => PackageOrderPresenter::collectionHasPackage($dayOrders),
            'groups' => $groups,
            'ungrouped' => $ungrouped,
        ];
    }

    protected function formatOrderNode(Order $order, bool $isUngrouped): array
    {
        $party = $order->partyPayload();

        return array_merge([
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'quantity' => $order->quantity,
            'total_amount' => $order->total_amount,
            'amount_paid' => $party['amount_paid'],
            'amount_due' => $party['amount_due'],
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'],
            'payment_method_label' => $party['payment_method_label'],
            'address' => $order->address,
            'delivery_date' => $order->delivery_date->toDateString(),
            'is_ungrouped' => $isUngrouped,
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'order_group_id' => $order->orderGroup?->id,
            'group_name' => $order->orderGroup?->name,
        ], PackageOrderPresenter::fields($order));
    }

    public function getFlatOrdersProperty(): array
    {
        $rows = [];
        foreach ($this->dateSections as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['orders'] as $order) {
                    $rows[] = array_merge($order, [
                        'order_group_id' => $group['id'],
                        'group_name' => $group['name'],
                        'delivery_date' => $section['date'],
                    ]);
                }
            }
            foreach ($section['ungrouped'] as $order) {
                $rows[] = array_merge($order, [
                    'order_group_id' => null,
                    'group_name' => 'Ungrouped',
                    'delivery_date' => $section['date'],
                ]);
            }
        }

        return $rows;
    }

    public function getKitchenOptionsProperty(): array
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])
            ->all();
    }

    public function getHasUnassignedGroupsProperty(): bool
    {
        return collect($this->dateSections)
            ->flatMap(fn (array $s) => $s['groups'])
            ->contains(fn (array $g) => empty($g['kitchen_id']));
    }

    public function exportExcel(): StreamedResponse
    {
        $orders = $this->baseOrderQuery()
            ->with(['menuItem', 'user', 'orderGroup', 'packageSubscription.package'])
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->get();

        if ($this->lateOnly) {
            $lateIds = OpsSlaBoard::lateToPack()->pluck('id')->all();
            $orders = $orders->filter(fn (Order $o) => in_array($o->id, $lateIds, true))->values();
        }

        return OrdersExcelExport::download($orders, 'active-orders-'.now('Asia/Dhaka')->format('Y-m-d').'.csv');
    }

    protected function dateLabel(string $date): string
    {
        $today = now('Asia/Dhaka')->toDateString();
        $tomorrow = now('Asia/Dhaka')->copy()->addDay()->toDateString();

        if ($date === $today) {
            return 'Today';
        }

        if ($date === $tomorrow) {
            return 'Tomorrow';
        }

        return Carbon::parse($date, 'Asia/Dhaka')->format('l, F-j');
    }

    public function render()
    {
        return view('livewire.operation.active-orders', [
            'kitchenOptions' => $this->kitchenOptions,
            'hasUnassignedGroups' => $this->hasUnassignedGroups,
        ])->layout('layouts.private.app', ['title' => 'Active Orders']);
    }
}
