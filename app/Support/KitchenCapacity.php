<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;

class KitchenCapacity
{
    /**
     * Groups currently occupying a kitchen slot: assigned and still holding
     * undispatched active orders (accepted / preparing — not yet handed to rider).
     */
    public static function openGroupCount(int $kitchenId): int
    {
        return OrderGroup::query()
            ->where('kitchen_id', $kitchenId)
            ->whereHas('orders', function ($query) {
                $query->whereIn('order_status', Order::ACTIVE_STATUSES)
                    ->whereNull('dispatched_at');
            })
            ->count();
    }

    public static function effectiveAllowedOpenGroups(User $kitchen): int
    {
        if ($kitchen->allowed_open_groups !== null) {
            return max(0, (int) $kitchen->allowed_open_groups);
        }

        return MiddoSettings::defaultAllowedOpenGroupsForTier(
            KitchenTier::normalize($kitchen->kitchen_tier)
        );
    }

    public static function remainingSlots(User $kitchen): int
    {
        return max(0, self::effectiveAllowedOpenGroups($kitchen) - self::openGroupCount((int) $kitchen->id));
    }

    public static function canAccept(User $kitchen): bool
    {
        return self::remainingSlots($kitchen) > 0;
    }

    public static function assertCanAccept(User $kitchen): void
    {
        $allowed = self::effectiveAllowedOpenGroups($kitchen);
        $open = self::openGroupCount((int) $kitchen->id);

        if ($open >= $allowed) {
            throw new \RuntimeException(sprintf(
                'Kitchen is at capacity (%d of %d open groups). Finish or release a group before accepting another.',
                $open,
                $allowed
            ));
        }
    }
}
