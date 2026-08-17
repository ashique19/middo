<?php

namespace App\Support;

use App\Models\KitchenBoxRequestBox;
use App\Models\KitchenWarehouseHandoff;
use App\Models\MiddoBox;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Boxes a rider should act on: ops-assigned warehouse stock, kitchen→ops,
 * corporate empty-box collect, plus custody.
 */
class RiderPendingBoxes
{
    public static function countForRider(int $riderId): int
    {
        return self::boxIdsForRider($riderId)->count();
    }

    /**
     * Unassigned kitchen→ops runs waiting for ops to pick a rider (not rider-visible).
     */
    public static function unassignedKitchenToOpsCount(): int
    {
        if (! Schema::hasTable('kitchen_warehouse_handoffs')) {
            return 0;
        }

        return KitchenWarehouseHandoff::query()
            ->where('status', KitchenWarehouseHandoff::STATUS_RUN_REQUESTED)
            ->whereNull('rider_id')
            ->whereHas(
                'box',
                fn ($q) => $q->whereColumn('middo_boxes.kitchen_id', 'middo_boxes.held_by_user_id')
                    ->whereIn('asset_status', ['active', 'damaged'])
            )
            ->count();
    }

    /**
     * @deprecated Riders no longer claim kitchen→ops; ops assigns.
     */
    public static function claimableKitchenToOpsCount(): int
    {
        return 0;
    }

    /**
     * @return Collection<int, int>
     */
    public static function boxIdsForRider(int $riderId): Collection
    {
        $heldIds = MiddoBox::query()
            ->where('held_by_user_id', $riderId)
            ->whereIn('asset_status', ['active', 'damaged'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $stagedOpsToKitchenIds = collect();
        if (Schema::hasTable('kitchen_box_request_boxes')) {
            $stagedOpsToKitchenIds = KitchenBoxRequestBox::query()
                ->where('rider_id', $riderId)
                ->where('status', KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP)
                ->whereHas(
                    'box',
                    fn ($q) => $q->where('asset_status', 'at_middo_warehouse')
                        ->whereNull('held_by_user_id')
                )
                ->pluck('middo_box_id')
                ->map(fn ($id) => (int) $id);
        }

        $kitchenToOpsIds = collect();
        if (Schema::hasTable('kitchen_warehouse_handoffs')) {
            $kitchenToOpsIds = KitchenWarehouseHandoff::query()
                ->where('rider_id', $riderId)
                ->whereIn('status', [
                    KitchenWarehouseHandoff::STATUS_RUN_CLAIMED,
                    KitchenWarehouseHandoff::STATUS_DISPATCHED,
                    KitchenWarehouseHandoff::STATUS_IN_TRANSIT,
                    KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS,
                ])
                ->pluck('middo_box_id')
                ->map(fn ($id) => (int) $id);
        }

        $emptyPickupIds = collect();
        if (Schema::hasColumn('middo_boxes', 'pickup_rider_id')) {
            $emptyPickupIds = MiddoBox::query()
                ->where('pickup_rider_id', $riderId)
                ->where('held_by_user_id', '!=', $riderId)
                ->whereHas('heldByUser.role', fn ($q) => $q->where('name', 'corporate'))
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
        }

        return $heldIds
            ->concat($stagedOpsToKitchenIds)
            ->concat($kitchenToOpsIds)
            ->concat($emptyPickupIds)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, MiddoBox>
     */
    public static function boxesForRider(int $riderId): Collection
    {
        $ids = self::boxIdsForRider($riderId);
        if ($ids->isEmpty()) {
            return collect();
        }

        return MiddoBox::query()
            ->with([
                'kitchen',
                'heldByUser.role',
                'pickupRider',
                'warehouseHandoff.kitchen',
                'warehouseHandoff.rider',
                'orderMiddoBoxes.order.menuItem',
                'orderMiddoBoxes.order.user',
                'orderMiddoBoxes.order.orderGroup.kitchen',
            ])
            ->whereIn('id', $ids)
            ->orderBy('qr_code_id')
            ->get();
    }

    /**
     * Ops→kitchen staged warehouse pickups keyed by middo_box_id.
     *
     * @return Collection<int, KitchenBoxRequestBox>
     */
    public static function stagedLinksForRider(int $riderId): Collection
    {
        if (! Schema::hasTable('kitchen_box_request_boxes')) {
            return collect();
        }

        return KitchenBoxRequestBox::query()
            ->with(['request.kitchen', 'rider'])
            ->where('rider_id', $riderId)
            ->where('status', KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP)
            ->whereHas(
                'box',
                fn ($q) => $q->where('asset_status', 'at_middo_warehouse')
                    ->whereNull('held_by_user_id')
            )
            ->orderBy('id')
            ->get()
            ->keyBy('middo_box_id');
    }

    /**
     * Kitchen→ops handoffs assigned to this rider, keyed by middo_box_id.
     *
     * @return Collection<int, KitchenWarehouseHandoff>
     */
    public static function kitchenToOpsLinksForRider(int $riderId): Collection
    {
        if (! Schema::hasTable('kitchen_warehouse_handoffs')) {
            return collect();
        }

        $ids = self::boxIdsForRider($riderId);
        if ($ids->isEmpty()) {
            return collect();
        }

        return KitchenWarehouseHandoff::query()
            ->with(['kitchen', 'rider', 'box'])
            ->where('rider_id', $riderId)
            ->whereIn('middo_box_id', $ids)
            ->whereIn('status', [
                KitchenWarehouseHandoff::STATUS_RUN_CLAIMED,
                KitchenWarehouseHandoff::STATUS_DISPATCHED,
                KitchenWarehouseHandoff::STATUS_IN_TRANSIT,
                KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS,
            ])
            ->orderBy('id')
            ->get()
            ->keyBy('middo_box_id');
    }

    /**
     * @deprecated Use kitchenToOpsLinksForRider
     *
     * @return Collection<int, KitchenWarehouseHandoff>
     */
    public static function stagedWarehouseReturnLinksForRider(int $riderId): Collection
    {
        return self::kitchenToOpsLinksForRider($riderId)
            ->filter(fn (KitchenWarehouseHandoff $h) => $h->status === KitchenWarehouseHandoff::STATUS_DISPATCHED);
    }
}
