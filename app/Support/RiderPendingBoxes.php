<?php

namespace App\Support;

use App\Models\KitchenBoxRequestBox;
use App\Models\MiddoBox;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Boxes a rider should act on: warehouse stock staged for their pickup,
 * plus anything already in their custody.
 */
class RiderPendingBoxes
{
    public static function countForRider(int $riderId): int
    {
        return self::boxIdsForRider($riderId)->count();
    }

    /**
     * @return Collection<int, int>
     */
    public static function boxIdsForRider(int $riderId): Collection
    {
        $heldIds = MiddoBox::query()
            ->where('held_by_user_id', $riderId)
            ->where('asset_status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $stagedIds = collect();
        if (Schema::hasTable('kitchen_box_request_boxes')) {
            $stagedIds = KitchenBoxRequestBox::query()
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

        return $heldIds->concat($stagedIds)->unique()->values();
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
                'orderMiddoBoxes.order.menuItem',
                'orderMiddoBoxes.order.user',
                'orderMiddoBoxes.order.orderGroup.kitchen',
            ])
            ->whereIn('id', $ids)
            ->orderBy('qr_code_id')
            ->get();
    }

    /**
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
}
