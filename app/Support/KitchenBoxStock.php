<?php

namespace App\Support;

use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;

/**
 * Middo-box inventory gates for kitchen accept / dashboard warnings.
 * One sendable box is required per plate (order quantity).
 */
class KitchenBoxStock
{
    public static function sendableCount(int $kitchenId): int
    {
        return MiddoBox::query()->sendableAtKitchen($kitchenId)->count();
    }

    /**
     * Plates already accepted and not yet dispatched (still need a box at the kitchen).
     */
    public static function committedPlateQty(int $kitchenId): int
    {
        return (int) Order::query()
            ->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $kitchenId))
            ->whereIn('order_status', Order::ACTIVE_STATUSES)
            ->whereNull('dispatched_at')
            ->sum('quantity');
    }

    public static function groupPlateQty(OrderGroup $group): int
    {
        $group->loadMissing('orders');

        return (int) $group->orders
            ->where('order_status', '!=', 'cancelled')
            ->sum('quantity');
    }

    public static function remainingPlateCapacity(int $kitchenId): int
    {
        return max(0, self::sendableCount($kitchenId) - self::committedPlateQty($kitchenId));
    }

    /**
     * Dashboard banner: stock below the kitchen's allowed open-group capacity
     * (product "allowed order quantity" / allowed_open_groups).
     */
    public static function hasInsufficientStockVsAllowed(User $kitchen): bool
    {
        $allowed = KitchenCapacity::effectiveAllowedOpenGroups($kitchen);

        return self::sendableCount((int) $kitchen->id) < $allowed;
    }

    public static function canAcceptGroup(User $kitchen, OrderGroup $group): bool
    {
        $needed = self::groupPlateQty($group);
        if ($needed < 1) {
            return true;
        }

        return $needed <= self::remainingPlateCapacity((int) $kitchen->id);
    }

    public static function assertCanAcceptGroup(User $kitchen, OrderGroup $group): void
    {
        $needed = self::groupPlateQty($group);
        if ($needed < 1) {
            return;
        }

        $sendable = self::sendableCount((int) $kitchen->id);
        $committed = self::committedPlateQty((int) $kitchen->id);
        $remaining = max(0, $sendable - $committed);

        if ($needed > $remaining) {
            throw new \RuntimeException(sprintf(
                'Insufficient Middo boxes in stock (%d available for new plates, need %d). Contact Ops for boxes before accepting.',
                $remaining,
                $needed
            ));
        }
    }

    public static function dashboardWarningMessage(): string
    {
        return 'You have insufficient middo box in stock. contact Ops for box. You can accept order only after you have Middo box in stock.';
    }
}
