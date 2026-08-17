<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Area-scoped helpers for delivery riders (R1).
 *
 * Lunch and custom runs are ops-assigned; riders never first-claim an open pool.
 */
class DeliveryAreaScope
{
    /**
     * Kitchen-dispatched list: only runs ops assigned to this rider.
     */
    public static function applyKitchenDispatchedVisibleToRider(Builder $query, User $rider): Builder
    {
        return $query->where('delivery_rider_id', (int) $rider->id);
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
        return $order->isAssignedToRider((int) $rider->id);
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
