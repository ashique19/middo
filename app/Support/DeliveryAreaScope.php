<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Area-scoped visibility for delivery riders (R1).
 *
 * Riders with service areas only see / accept work in those areas.
 * Orders with no area (order + group) stay in an open pool any rider can claim
 * so unscoped legacy work does not get stuck.
 */
class DeliveryAreaScope
{
    /**
     * Kitchen-dispatched list: rider's own accepted runs + unclaimed runs they may take.
     */
    public static function applyKitchenDispatchedVisibleToRider(Builder $query, User $rider): Builder
    {
        $riderId = (int) $rider->id;
        $areaIds = $rider->serviceAreaIds();

        return $query->where(function (Builder $q) use ($riderId, $areaIds) {
            $q->where('delivery_rider_id', $riderId)
                ->orWhere(function (Builder $pending) use ($areaIds) {
                    $pending->whereNull('delivery_rider_id')
                        ->where(function (Builder $areaQ) use ($areaIds) {
                            // Unscoped: neither order nor group has an area.
                            $areaQ->where(function (Builder $unscoped) {
                                $unscoped->whereNull('orders.area_id')
                                    ->where(function (Builder $groupArea) {
                                        $groupArea->whereDoesntHave('orderGroup')
                                            ->orWhereHas('orderGroup', fn (Builder $g) => $g->whereNull('area_id'));
                                    });
                            });

                            if ($areaIds === []) {
                                return;
                            }

                            $areaQ->orWhereIn('orders.area_id', $areaIds)
                                ->orWhere(function (Builder $fallback) use ($areaIds) {
                                    $fallback->whereNull('orders.area_id')
                                        ->whereHas('orderGroup', fn (Builder $g) => $g->whereIn('area_id', $areaIds));
                                });
                        });
                });
        });
    }

    public static function orderAreaId(Order $order): ?int
    {
        $order->loadMissing('orderGroup');

        if ($order->area_id) {
            return (int) $order->area_id;
        }

        $groupArea = $order->orderGroup?->area_id;

        return $groupArea !== null ? (int) $groupArea : null;
    }

    public static function riderMayAccept(Order $order, User $rider): bool
    {
        $areaId = self::orderAreaId($order);

        // Unscoped work stays first-claim for any active rider.
        if ($areaId === null) {
            return true;
        }

        return $rider->servesArea($areaId);
    }

    /**
     * Active delivery riders serving an area.
     *
     * @return list<User>
     */
    public static function ridersForArea(?int $areaId): array
    {
        if ($areaId === null || ! Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->with(['role', 'areas'])
            ->whereHas('role', fn ($q) => $q->where('name', 'delivery'))
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $u) => $u->servesArea($areaId))
            ->values()
            ->all();
    }
}
