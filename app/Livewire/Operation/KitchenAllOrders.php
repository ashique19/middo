<?php

namespace App\Livewire\Operation;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class KitchenAllOrders extends Component
{
    use WithPagination;

    public User $kitchen;

    public function mount(User $kitchen): void
    {
        $kitchen->load('role');

        abort_unless($kitchen->role?->name === 'kitchen', 404);

        $this->kitchen = $kitchen;
    }

    protected function formatOrderNode(Order $order): array
    {
        return [
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'delivery_date' => $order->delivery_date->toDateString(),
            'quantity' => $order->quantity,
            'order_status' => $order->order_status,
            'customer_name' => trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? '')) ?: 'N/A',
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
        ];
    }

    protected function dateLabel(string $date): string
    {
        $today = now('Asia/Dhaka')->toDateString();
        $yesterday = now('Asia/Dhaka')->copy()->subDay()->toDateString();
        $tomorrow = now('Asia/Dhaka')->copy()->addDay()->toDateString();

        if ($date === $today) {
            return 'Today';
        }

        if ($date === $yesterday) {
            return 'Yesterday';
        }

        if ($date === $tomorrow) {
            return 'Tomorrow';
        }

        return Carbon::parse($date, 'Asia/Dhaka')->format('l, F-j');
    }

    protected function buildGroupNodes($groups): array
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

        $offset = ($groups->currentPage() - 1) * $groups->perPage();

        return collect($groups->items())
            ->values()
            ->map(function (OrderGroup $group, int $index) use ($colorPalette, $offset) {
                $orders = $group->orders
                    ->sort(function (Order $a, Order $b) {
                        $dateCompare = $b->delivery_date <=> $a->delivery_date;

                        if ($dateCompare !== 0) {
                            return $dateCompare;
                        }

                        return $a->delivery_time <=> $b->delivery_time;
                    })
                    ->values();

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'menu_name' => $group->menuItem?->name ?? 'Unknown',
                    'date_label' => $this->dateLabel($group->delivery_date->toDateString()),
                    'total_quantity' => $orders->sum('quantity'),
                    'color' => $colorPalette[($offset + $index) % count($colorPalette)],
                    'orders' => $orders
                        ->map(fn (Order $order) => $this->formatOrderNode($order))
                        ->all(),
                ];
            })
            ->all();
    }

    public function render()
    {
        $groups = OrderGroup::with([
            'menuItem',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'user'])
                ->orderByDesc('delivery_date')
                ->orderBy('delivery_time'),
        ])
            ->where('kitchen_id', $this->kitchen->id)
            ->orderByDesc('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $groupNodes = $this->buildGroupNodes($groups);

        return view('livewire.operation.kitchen-all-orders', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
        ])->layout('layouts.private.app', [
            'title' => $this->kitchen->name.' — All Orders',
        ]);
    }
}
