<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Concerns\WithOrdersListView;
use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Support\DispatchDeadline;
use App\Support\OrderGroupKitchenAssignment;
use App\Support\OrdersExcelExport;
use App\Support\OrderTransition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActiveOrders extends Component
{
    use FormatsOrderGroups;
    use WithOrdersListView;
    use WithPagination;

    public int $boxInventoryCount = 0;

    public ?string $nextDispatchDeadlineIso = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public ?int $shortageGroupId = null;

    public string $shortageReason = '';

    /** @var int[] */
    protected array $dispatchedOrderIds = [];

    public function mount(): void
    {
        $this->refreshBoxInventory();
    }

    #[On('order-dispatched')]
    public function onOrderDispatched(): void
    {
        $this->statusMessage = 'Order dispatched successfully.';
        $this->errorMessage = null;
        $this->refreshBoxInventory();
        $this->resetPage();
    }

    protected function refreshBoxInventory(): void
    {
        $this->boxInventoryCount = MiddoBox::query()
            ->atKitchen(Auth::id())
            ->whereDoesntHave('orderMiddoBoxes')
            ->count();
    }

    public function markReady(int $orderId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $kitchenId = (int) Auth::id();

        try {
            DB::transaction(function () use ($orderId, $kitchenId) {
                $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
                if (! $order) {
                    throw new \RuntimeException('Order not found.');
                }

                $groupKitchenId = DB::table('order_group_orders')
                    ->join('order_groups', 'order_groups.id', '=', 'order_group_orders.order_group_id')
                    ->where('order_group_orders.order_id', $order->id)
                    ->value('order_groups.kitchen_id');

                if ((int) $groupKitchenId !== $kitchenId) {
                    throw new \RuntimeException('Order not found for your kitchen.');
                }

                OrderTransition::apply($order, OrderTransition::READY, [
                    'updated_by' => $kitchenId,
                ]);
            });

            $this->statusMessage = "Order #{$orderId} marked ready for rider pickup.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not mark order ready.';
        }
    }

    public function markGroupReady(int $orderGroupId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $kitchenId = (int) Auth::id();

        try {
            $count = DB::transaction(function () use ($orderGroupId, $kitchenId) {
                $group = OrderGroup::query()
                    ->with('orders')
                    ->whereKey($orderGroupId)
                    ->lockForUpdate()
                    ->first();

                if (! $group || (int) $group->kitchen_id !== $kitchenId) {
                    throw new \RuntimeException('Order group not found for your kitchen.');
                }

                $updated = 0;
                foreach ($group->orders as $order) {
                    if ($order->order_status !== OrderTransition::PROCESSING || $order->dispatched_at !== null) {
                        continue;
                    }
                    $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                    if ($locked && OrderTransition::can($locked, OrderTransition::READY)) {
                        OrderTransition::apply($locked, OrderTransition::READY, [
                            'updated_by' => $kitchenId,
                        ]);
                        $updated++;
                    }
                }

                if ($updated === 0) {
                    throw new \RuntimeException('No processing orders left to mark ready in this group.');
                }

                return $updated;
            });

            $this->statusMessage = "Marked {$count} order(s) ready in this group.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not mark group ready.';
        }
    }

    public function releaseGroup(int $orderGroupId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $kitchen = Auth::user();

        try {
            $group = OrderGroup::query()->findOrFail($orderGroupId);
            OrderGroupKitchenAssignment::release($group, $kitchen);
            $this->statusMessage = "Released {$group->name} back to the Middo pool.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not release this group.';
        }
    }

    public function openShortage(int $orderGroupId): void
    {
        $this->errorMessage = null;
        $this->shortageGroupId = $orderGroupId;
        $this->shortageReason = '';
    }

    public function cancelShortage(): void
    {
        $this->shortageGroupId = null;
        $this->shortageReason = '';
    }

    public function confirmShortage(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $kitchen = Auth::user();

        if (! $kitchen || ! $this->shortageGroupId) {
            return;
        }

        try {
            $this->validate([
                'shortageReason' => 'required|string|min:3|max:255',
            ]);

            $group = OrderGroup::query()->findOrFail($this->shortageGroupId);
            OrderGroupKitchenAssignment::reportShortage($group, $kitchen, $this->shortageReason);
            $this->statusMessage = "Shortage reported for {$group->name}. Group returned to Middo pool.";
            $this->cancelShortage();
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not report shortage.';
        }
    }

    protected function formatOrderNode(Order $order): array
    {
        $deadline = DispatchDeadline::forOrder($order);
        $dispatched = in_array($order->id, $this->dispatchedOrderIds, true);
        $status = (string) $order->order_status;

        return array_merge($this->baseOrderNode($order), [
            'box_low' => $order->quantity > $this->boxInventoryCount,
            'dispatch_deadline_iso' => $deadline->toIso8601String(),
            'dispatch_deadline_label' => $deadline->format('g:i A'),
            'can_mark_ready' => ! $dispatched && $status === OrderTransition::PROCESSING,
            'can_dispatch' => ! $dispatched && $status === OrderTransition::READY,
            'dispatched' => $dispatched,
            'is_ready' => $status === OrderTransition::READY,
        ]);
    }

    public function render()
    {
        $kitchenId = Auth::id();
        $today = now('Asia/Dhaka')->toDateString();
        $this->refreshBoxInventory();

        $groups = OrderGroup::with([
            'menuItem',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'user', 'packageSubscription.package'])
                ->active()
                ->orderBy('delivery_time'),
        ])
            ->where('kitchen_id', $kitchenId)
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->active())
            ->orderBy('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $allOrders = $groups->getCollection()->flatMap->orders;
        $nextDeadline = DispatchDeadline::earliestForOrders($allOrders);
        $this->nextDispatchDeadlineIso = $nextDeadline?->toIso8601String();

        $this->dispatchedOrderIds = Order::query()
            ->whereIn('id', $allOrders->pluck('id')->filter()->all() ?: [0])
            ->whereNotNull('dispatched_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $offset = ($groups->currentPage() - 1) * $groups->perPage();
        $groupNodes = $this->buildGroupNodes($groups->getCollection(), $offset);

        $groupNodes = collect($groupNodes)
            ->map(function (array $node) use ($groups) {
                $group = $groups->getCollection()->firstWhere('id', $node['id']);
                $canRelease = $group ? OrderGroupKitchenAssignment::canRelease($group) : false;
                $hasProcessing = collect($node['orders'])->contains(fn (array $o) => ($o['order_status'] ?? '') === OrderTransition::PROCESSING);

                return array_merge($node, [
                    'can_release' => $canRelease,
                    'can_mark_group_ready' => $hasProcessing,
                    'can_report_shortage' => $canRelease,
                ]);
            })
            ->all();

        $flatOrders = collect($groupNodes)
            ->flatMap(fn (array $group) => collect($group['orders'])->map(
                fn (array $order) => array_merge($order, ['group_name' => $group['name']])
            ))
            ->values()
            ->all();

        return view('livewire.kitchen.active-orders', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
            'flatOrders' => $flatOrders,
        ])->layout('layouts.private.app', ['title' => 'My Active Orders']);
    }

    public function exportExcel(): StreamedResponse
    {
        $kitchenId = Auth::id();
        $today = now('Asia/Dhaka')->toDateString();

        $orders = Order::query()
            ->with(['menuItem', 'user', 'orderGroup'])
            ->active()
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $kitchenId))
            ->orderBy('delivery_date')
            ->orderBy('delivery_time')
            ->get();

        return OrdersExcelExport::download($orders, 'kitchen-active-orders-'.now('Asia/Dhaka')->format('Y-m-d').'.csv');
    }
}
