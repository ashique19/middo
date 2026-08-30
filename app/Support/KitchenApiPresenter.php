<?php

namespace App\Support;

use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\StaffAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KitchenApiPresenter
{
    public static function user(User $user): array
    {
        $user->loadMissing('role');

        $areaName = $user->area_id
            ? Area::query()->whereKey($user->area_id)->value('name')
            : ($user->getAttributes()['area'] ?? null);
        $cityName = $user->city_id
            ? City::query()->whereKey($user->city_id)->value('name')
            : ($user->getAttributes()['city'] ?? null);

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'balance' => (int) $user->balance,
            'address' => $user->address,
            'area' => $areaName,
            'city' => $cityName,
            'area_id' => $user->area_id,
            'city_id' => $user->city_id,
            'kitchen_tier' => $user->kitchen_tier ?? null,
            'role' => $user->role?->name,
        ];
    }

    public static function dashboardTile(string $key, string $label, int $count): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
        ];
    }

    public static function alert(StaffAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'title' => $alert->title,
            'body' => $alert->body,
            'order_group_id' => $alert->order_group_id,
            'meta' => $alert->meta ?? [],
            'read_at' => $alert->read_at?->toIso8601String(),
            'is_unread' => $alert->read_at === null,
            'created_at' => $alert->created_at?->toIso8601String(),
        ];
    }

    /**
     * Kitchen-facing order row: never customer name or street address.
     */
    public static function order(Order $order): array
    {
        $party = $order->partyPayload();

        return array_merge([
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'delivery_date' => $order->delivery_date?->toDateString(),
            'quantity' => (int) $order->quantity,
            'order_status' => $order->order_status,
            'area_name' => self::areaNameForOrder($order),
            'amount_paid' => $party['amount_paid'] ?? null,
            'amount_due' => $party['amount_due'] ?? null,
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'] ?? null,
            'payment_method_label' => $party['payment_method_label'] ?? null,
            'total_amount' => (int) $order->total_amount,
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'menu_item_id' => $order->menu_item_id,
            'order_group_id' => $order->orderGroup?->id,
            'rider_name' => $order->deliveryRider?->name,
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'can_mark_ready' => $order->order_status === OrderTransition::PROCESSING
                && $order->dispatched_at === null,
            'can_dispatch' => $order->order_status === OrderTransition::RIDER_ASSIGNED
                && $order->delivery_rider_id
                && $order->dispatched_at === null,
            'awaiting_rider_claim' => $order->order_status === OrderTransition::READY
                && $order->dispatched_at === null,
        ], PackageOrderPresenter::fields($order));
    }

    public static function areaNameForOrder(Order $order): string
    {
        $name = $order->area?->name
            ?? $order->orderGroup?->area?->name;

        return is_string($name) && trim($name) !== '' ? trim($name) : '—';
    }

    public static function dateLabel(string $date): string
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

    /**
     * @param  Collection<int, OrderGroup>|iterable  $groups
     * @return list<array<string, mixed>>
     */
    public static function orderGroups($groups): array
    {
        return collect($groups)
            ->values()
            ->map(function (OrderGroup $group) {
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
                    ->map(function (Order $order) use ($group) {
                        if (! $order->relationLoaded('orderGroup')) {
                            $order->setRelation('orderGroup', $group);
                        }

                        return self::order($order);
                    })
                    ->all();

                $hasProcessing = collect($orderNodes)->contains(
                    fn (array $o) => ($o['order_status'] ?? '') === OrderTransition::PROCESSING
                );

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'menu_id' => $group->menu_id,
                    'menu_name' => $group->menuItem?->name ?? 'Unknown',
                    'kitchen_id' => $group->kitchen_id,
                    'date_label' => self::dateLabel($group->delivery_date->toDateString()),
                    'delivery_date' => $group->delivery_date->toDateString(),
                    'total_quantity' => (int) $orders->sum('quantity'),
                    'package_source' => PackageOrderPresenter::groupSource($orderNodes),
                    'has_package_orders' => PackageOrderPresenter::collectionHasPackage($orderNodes),
                    'can_release' => OrderGroupKitchenAssignment::canRelease($group),
                    'can_mark_group_ready' => $hasProcessing,
                    'can_report_shortage' => OrderGroupKitchenAssignment::canRelease($group),
                    'orders' => $orderNodes,
                ];
            })
            ->filter(fn (array $node) => count($node['orders']) > 0)
            ->values()
            ->all();
    }

    public static function menuItem(MenuItem $item): array
    {
        $image = $item->thumbnail;
        if ($image && ! preg_match('/^https?:\/\//', (string) $image)) {
            $image = asset(ltrim((string) $image, '/'));
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'summary' => $item->summary,
            'price' => (int) $item->price,
            'thumbnail' => $image,
            'is_featured' => (bool) $item->is_featured,
            'diet_tag' => $item->diet_tag ?? null,
        ];
    }

    public static function box(MiddoBox $box): array
    {
        return [
            'id' => $box->id,
            'qr_code_id' => $box->qr_code_id,
            'asset_status' => $box->asset_status,
            'kitchen_id' => $box->kitchen_id,
            'held_by_user_id' => $box->held_by_user_id,
            'updated_at' => $box->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $paginator
     * @return array<string, mixed>
     */
    public static function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
