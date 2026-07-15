<?php

namespace App\Livewire\Kitchen\Concerns;

use App\Models\Order;
use App\Models\OrderGroup;
use Carbon\Carbon;
use Illuminate\Support\Collection;

trait FormatsOrderGroups
{
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

    protected function formatOrderNode(Order $order): array
    {
        return $this->baseOrderNode($order);
    }

    protected function baseOrderNode(Order $order): array
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

    /**
     * @param  Collection<int, OrderGroup>|iterable  $groups
     * @return array<int, array<string, mixed>>
     */
    protected function buildGroupNodes($groups, int $colorOffset = 0): array
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

        return collect($groups)
            ->values()
            ->map(function (OrderGroup $group, int $index) use ($colorPalette, $colorOffset) {
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
                    'menu_id' => $group->menu_id,
                    'menu_name' => $group->menuItem?->name ?? 'Unknown',
                    'date_label' => $this->dateLabel($group->delivery_date->toDateString()),
                    'delivery_date' => $group->delivery_date->toDateString(),
                    'total_quantity' => $orders->sum('quantity'),
                    'color' => $colorPalette[($colorOffset + $index) % count($colorPalette)],
                    'orders' => $orders
                        ->map(fn (Order $order) => $this->formatOrderNode($order))
                        ->all(),
                ];
            })
            ->all();
    }
}
