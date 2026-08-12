<?php

namespace App\Support;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
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

    /**
     * Ops→kitchen boxes currently on the way to this kitchen, grouped by holding rider.
     *
     * @return list<array{count: int, name: string, mobile: ?string, label: string}>
     */
    public static function opsIncomingNotices(int $kitchenId): array
    {
        $opsActions = [
            'rider_accepted_kitchen_stock',
            'handed_to_kitchen_stock',
            'dispatched_to_kitchen',
        ];

        $latestLogIds = MiddoBoxLog::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('middo_box_id')
            ->pluck('id');

        $visibleBoxIds = MiddoBoxLog::query()
            ->whereIn('id', $latestLogIds)
            ->whereIn('log_action', $opsActions)
            ->pluck('middo_box_id');

        if ($visibleBoxIds->isEmpty()) {
            return [];
        }

        return MiddoBox::query()
            ->with('heldByUser')
            ->incomingToKitchen($kitchenId)
            ->whereIn('id', $visibleBoxIds)
            ->get()
            ->groupBy(fn (MiddoBox $box) => (int) ($box->held_by_user_id ?? 0))
            ->map(function ($boxes) {
                $holder = $boxes->first()?->heldByUser;
                $count = $boxes->count();
                $name = $holder?->name ?: 'rider';
                $mobile = $holder?->mobile ? (string) $holder->mobile : null;
                $label = $count.' '.str('box')->plural($count).' incoming from Ops, by '.$name
                    .($mobile ? ' ('.$mobile.')' : '');

                return [
                    'count' => $count,
                    'name' => $name,
                    'mobile' => $mobile,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }
}
