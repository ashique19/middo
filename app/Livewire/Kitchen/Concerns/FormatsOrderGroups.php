<?php

namespace App\Livewire\Kitchen\Concerns;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Support\PackageOrderPresenter;
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
        $party = $order->partyPayload();

        return array_merge([
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'delivery_date' => $order->delivery_date->toDateString(),
            'quantity' => $order->quantity,
            'order_status' => $order->order_status,
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'account_holder_mobile' => $party['account_holder_mobile'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'amount_paid' => $party['amount_paid'],
            'amount_due' => $party['amount_due'],
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'],
            'payment_method_label' => $party['payment_method_label'],
            'total_amount' => $order->total_amount,
            'address' => $order->address,
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
        ], PackageOrderPresenter::fields($order));
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
                    ->filter(fn (Order $order) => $order->order_status !== 'cancelled')
                    ->sort(function (Order $a, Order $b) {
                        $dateCompare = $b->delivery_date <=> $a->delivery_date;

                        if ($dateCompare !== 0) {
                            return $dateCompare;
                        }

                        return $a->delivery_time <=> $b->delivery_time;
                    })
                    ->values();

                $orderNodes = $orders
                    ->map(fn (Order $order) => $this->formatOrderNode($order))
                    ->all();

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'menu_id' => $group->menu_id,
                    'menu_name' => $group->menuItem?->name ?? 'Unknown',
                    'date_label' => $this->dateLabel($group->delivery_date->toDateString()),
                    'delivery_date' => $group->delivery_date->toDateString(),
                    'total_quantity' => $orders->sum('quantity'),
                    'color' => $colorPalette[($colorOffset + $index) % count($colorPalette)],
                    'package_source' => PackageOrderPresenter::groupSource($orderNodes),
                    'has_package_orders' => PackageOrderPresenter::collectionHasPackage($orderNodes),
                    'orders' => $orderNodes,
                ];
            })
            ->filter(fn (array $node) => count($node['orders']) > 0)
            ->values()
            ->all();
    }
}
