<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Support\DispatchDeadline;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ActiveOrders extends Component
{
    use FormatsOrderGroups;
    use WithPagination;

    public int $boxInventoryCount = 0;

    public ?string $nextDispatchDeadlineIso = null;

    public ?string $statusMessage = null;

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
        $this->refreshBoxInventory();
        $this->resetPage();
    }

    protected function refreshBoxInventory(): void
    {
        $this->boxInventoryCount = MiddoBox::query()
            ->atKitchen(Auth::id())
            ->count();
    }

    protected function formatOrderNode(Order $order): array
    {
        $deadline = DispatchDeadline::forOrder($order);
        $dispatched = in_array($order->id, $this->dispatchedOrderIds, true);

        return array_merge($this->baseOrderNode($order), [
            'box_low' => $order->quantity > $this->boxInventoryCount,
            'dispatch_deadline_iso' => $deadline->toIso8601String(),
            'dispatch_deadline_label' => $deadline->format('g:i A'),
            'can_dispatch' => ! $dispatched && in_array($order->order_status, ['pending', 'processing'], true),
            'dispatched' => $dispatched,
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

        $allOrders = $groups->getCollection()->flatMap->orders;
        $nextDeadline = DispatchDeadline::earliestForOrders($allOrders);
        $this->nextDispatchDeadlineIso = $nextDeadline?->toIso8601String();

        $this->dispatchedOrderIds = MiddoBoxLog::query()
            ->whereIn('order_id', $allOrders->pluck('id')->filter()->all() ?: [0])
            ->where('log_action', 'picked_by_delivery_from_kitchen')
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $offset = ($groups->currentPage() - 1) * $groups->perPage();
        $groupNodes = $this->buildGroupNodes($groups->getCollection(), $offset);

        return view('livewire.kitchen.active-orders', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
        ])->layout('layouts.private.app', ['title' => 'My Active Orders']);
    }
}
