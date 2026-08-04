<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OpsAreaCoverage
{
    /**
     * Per-area coverage vs demand for a delivery date (Asia/Dhaka calendar day).
     *
     * @return list<array{
     *     area_id: int,
     *     area_name: string,
     *     city_name: string,
     *     kitchens: int,
     *     riders: int,
     *     orders: int,
     *     quantity: int,
     *     gap: bool,
     *     gap_reason: string|null
     * }>
     */
    public static function rows(?string $deliveryDate = null): array
    {
        $date = $deliveryDate ?: now('Asia/Dhaka')->toDateString();

        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');

        $kitchensByArea = $kitchenRoleId
            ? User::query()
                ->where('role_id', $kitchenRoleId)
                ->where('status', 'active')
                ->whereNotNull('area_id')
                ->selectRaw('area_id, count(*) as c')
                ->groupBy('area_id')
                ->pluck('c', 'area_id')
            : collect();

        /** @var Collection<int, User> $riders */
        $riders = $deliveryRoleId
            ? User::query()
                ->where('role_id', $deliveryRoleId)
                ->where('status', 'active')
                ->with(Schema::hasTable('area_user') ? ['areas'] : [])
                ->get(['id', 'area_id', 'role_id'])
            : collect();

        $orderStats = Order::query()
            ->whereDate('delivery_date', $date)
            ->where('order_status', '!=', 'cancelled')
            ->whereNotNull('area_id')
            ->selectRaw('area_id, count(*) as order_count, coalesce(sum(quantity), 0) as qty')
            ->groupBy('area_id')
            ->get()
            ->keyBy('area_id');

        $areas = Area::query()
            ->with('city')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($areas as $area) {
            $areaId = (int) $area->id;
            $kitchenCount = (int) ($kitchensByArea[$areaId] ?? 0);
            $riderCount = $riders->filter(fn (User $rider) => $rider->servesArea($areaId))->count();
            $stats = $orderStats->get($areaId);
            $orders = (int) ($stats->order_count ?? 0);
            $quantity = (int) ($stats->qty ?? 0);

            $gapReason = null;
            if ($orders > 0 && $kitchenCount === 0) {
                $gapReason = 'Orders with no kitchen in area';
            } elseif ($orders > 0 && $riderCount === 0) {
                $gapReason = 'Orders with no rider coverage';
            } elseif ($kitchenCount === 0 && $riderCount === 0) {
                $gapReason = 'No kitchen or rider';
            }

            $rows[] = [
                'area_id' => $areaId,
                'area_name' => $area->name,
                'city_name' => $area->city?->name ?? '—',
                'kitchens' => $kitchenCount,
                'riders' => $riderCount,
                'orders' => $orders,
                'quantity' => $quantity,
                'gap' => $gapReason !== null && ($orders > 0 || ($kitchenCount === 0 && $riderCount === 0)),
                'gap_reason' => $gapReason,
            ];
        }

        usort($rows, function (array $a, array $b) {
            if ($a['gap'] !== $b['gap']) {
                return $a['gap'] ? -1 : 1;
            }
            if ($a['orders'] !== $b['orders']) {
                return $b['orders'] <=> $a['orders'];
            }

            return strcmp($a['area_name'], $b['area_name']);
        });

        return $rows;
    }
}
