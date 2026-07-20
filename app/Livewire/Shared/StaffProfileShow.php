<?php

namespace App\Livewire\Shared;

use App\Models\Order;
use App\Models\User;
use App\Support\PackageOrderPresenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class StaffProfileShow extends Component
{
    use WithPagination;

    public User $staff;

    /** @var 'kitchen'|'delivery' */
    public string $staffRole;

    public function mount(?User $kitchen = null, ?User $delivery = null): void
    {
        $viewerRole = Auth::user()?->role?->name;
        abort_unless(in_array($viewerRole, ['admin', 'operation'], true), 403);

        $staff = $kitchen ?? $delivery;
        abort_unless($staff, 404);

        $expected = $kitchen ? 'kitchen' : 'delivery';
        $staff->load(['role', 'city', 'area']);
        abort_unless($staff->role?->name === $expected, 404);

        $this->staff = $staff;
        $this->staffRole = $expected;
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function backRoute(): string
    {
        return $this->staffRole === 'kitchen'
            ? route($this->rolePrefix().'.kitchens.'.($this->rolePrefix() === 'admin' ? 'active' : 'index'))
            : route($this->rolePrefix().'.orders.active');
    }

    public function kitchenOrdersRoute(): ?string
    {
        if ($this->staffRole !== 'kitchen') {
            return null;
        }

        return route($this->rolePrefix().'.kitchens.orders', $this->staff);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatOrder(Order $order): array
    {
        $party = $order->partyPayload();

        return array_merge([
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'delivery_date' => $order->delivery_date->toDateString(),
            'quantity' => $order->quantity,
            'order_status' => $order->order_status,
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'],
            'payment_method_label' => $party['payment_method_label'],
            'total_amount' => $order->total_amount,
            'address' => $order->address,
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'group_name' => $order->orderGroup?->name,
        ], PackageOrderPresenter::fields($order));
    }

    public function render()
    {
        $ordersQuery = Order::query()
            ->with(['menuItem', 'user', 'orderGroup', 'packageSubscription.package']);

        if ($this->staffRole === 'kitchen') {
            $ordersQuery->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $this->staff->id));
        } else {
            $ordersQuery->where('delivery_rider_id', $this->staff->id);
        }

        $orders = $ordersQuery
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(15);

        $orderRows = collect($orders->items())
            ->map(fn (Order $order) => $this->formatOrder($order))
            ->values()
            ->all();

        $today = now('Asia/Dhaka')->toDateString();
        $baseStats = Order::query();
        if ($this->staffRole === 'kitchen') {
            $baseStats->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $this->staff->id));
        } else {
            $baseStats->where('delivery_rider_id', $this->staff->id);
        }

        $stats = [
            'total_orders' => (clone $baseStats)->count(),
            'active_orders' => (clone $baseStats)
                ->whereIn('order_status', Order::ACTIVE_STATUSES)
                ->where('delivery_date', '>=', $today)
                ->count(),
            'delivered_orders' => (clone $baseStats)
                ->whereIn('order_status', ['delivered', 'delivered_and_paid'])
                ->count(),
        ];

        $title = ($this->staff->name ?: trim($this->staff->first_name.' '.$this->staff->last_name))
            .' · '.ucfirst($this->staffRole);

        return view('livewire.shared.staff.profile', [
            'orders' => $orders,
            'orderRows' => $orderRows,
            'stats' => $stats,
        ])->layout('layouts.private.app', ['title' => $title]);
    }
}
