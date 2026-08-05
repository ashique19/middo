<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
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
     *     riders_on_shift: int,
     *     orders: int,
     *     quantity: int,
     *     unclaimed_packed: int,
     *     aging_unclaimed: int,
     *     gap: bool,
     *     gap_reason: string|null
     * }>
     */
    public static function rows(?string $deliveryDate = null, ?Carbon $now = null): array
    {
        $date = $deliveryDate ?: now('Asia/Dhaka')->toDateString();
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');

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
                ->get(['id', 'area_id', 'role_id', 'rider_shift_status'])
            : collect();

        $orderStats = Order::query()
            ->whereDate('delivery_date', $date)
            ->where('order_status', '!=', 'cancelled')
            ->whereNotNull('area_id')
            ->selectRaw('area_id, count(*) as order_count, coalesce(sum(quantity), 0) as qty')
            ->groupBy('area_id')
            ->get()
            ->keyBy('area_id');

        $unclaimed = Order::query()
            ->with(['orderGroup'])
            ->whereDate('delivery_date', $date)
            ->where('order_status', 'packed')
            ->whereNotNull('dispatched_at')
            ->whereNull('delivery_rider_id')
            ->whereNotNull('area_id')
            ->get();

        $unclaimedByArea = [];
        $agingByArea = [];
        foreach ($unclaimed as $order) {
            $areaId = (int) $order->area_id;
            $unclaimedByArea[$areaId] = ($unclaimedByArea[$areaId] ?? 0) + 1;
            $sla = RiderAcceptSla::statusPayload($order, $now);
            if ($sla['aging']) {
                $agingByArea[$areaId] = ($agingByArea[$areaId] ?? 0) + 1;
            }
        }

        $areas = Area::query()
            ->with('city')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($areas as $area) {
            $areaId = (int) $area->id;
            $kitchenCount = (int) ($kitchensByArea[$areaId] ?? 0);
            $areaRiders = $riders->filter(fn (User $rider) => $rider->servesArea($areaId));
            $riderCount = $areaRiders->count();
            $onShift = $areaRiders->filter(fn (User $rider) => RiderShift::canAcceptNewRuns($rider->rider_shift_status ?? null))->count();
            $stats = $orderStats->get($areaId);
            $orders = (int) ($stats->order_count ?? 0);
            $quantity = (int) ($stats->qty ?? 0);
            $unclaimedPacked = (int) ($unclaimedByArea[$areaId] ?? 0);
            $agingUnclaimed = (int) ($agingByArea[$areaId] ?? 0);

            $gapReason = null;
            if ($agingUnclaimed > 0) {
                $gapReason = "{$agingUnclaimed} packed unclaimed aging";
            } elseif ($unclaimedPacked > 0 && $onShift === 0) {
                $gapReason = 'Unclaimed packed — no riders on shift';
            } elseif ($orders > 0 && $kitchenCount === 0) {
                $gapReason = 'Orders with no kitchen in area';
            } elseif ($orders > 0 && $riderCount === 0) {
                $gapReason = 'Orders with no rider coverage';
            } elseif ($kitchenCount === 0 && $riderCount === 0) {
                $gapReason = 'No kitchen or rider';
            }

            $gap = $gapReason !== null && (
                $orders > 0
                || $unclaimedPacked > 0
                || ($kitchenCount === 0 && $riderCount === 0)
            );

            $rows[] = [
                'area_id' => $areaId,
                'area_name' => $area->name,
                'city_name' => $area->city?->name ?? '—',
                'kitchens' => $kitchenCount,
                'riders' => $riderCount,
                'riders_on_shift' => $onShift,
                'orders' => $orders,
                'quantity' => $quantity,
                'unclaimed_packed' => $unclaimedPacked,
                'aging_unclaimed' => $agingUnclaimed,
                'gap' => $gap,
                'gap_reason' => $gapReason,
            ];
        }

        usort($rows, function (array $a, array $b) {
            if ($a['gap'] !== $b['gap']) {
                return $a['gap'] ? -1 : 1;
            }
            if ($a['aging_unclaimed'] !== $b['aging_unclaimed']) {
                return $b['aging_unclaimed'] <=> $a['aging_unclaimed'];
            }
            if ($a['orders'] !== $b['orders']) {
                return $b['orders'] <=> $a['orders'];
            }

            return strcmp($a['area_name'], $b['area_name']);
        });

        return $rows;
    }
}
