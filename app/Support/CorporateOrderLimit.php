<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

class CorporateOrderLimit
{
    public static function defaultMaxAllowed(): int
    {
        return MiddoSettings::maxOrderQtyAllowed();
    }

    public static function maxAllowed(?int $userId = null): int
    {
        if ($userId) {
            $override = User::query()->whereKey($userId)->value('max_order_qty_allowed');
            if ($override !== null && (int) $override > 0) {
                return max(1, (int) $override);
            }
        }

        return self::defaultMaxAllowed();
    }

    public static function existingQtyForDate(int $userId, string $date, ?int $excludeOrderId = null): int
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->whereDate('delivery_date', $date)
            ->where('order_status', '!=', 'cancelled');

        if ($excludeOrderId) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return (int) $query->sum('quantity');
    }

    public static function remainingQtyForDate(int $userId, string $date, ?int $excludeOrderId = null): int
    {
        return max(0, self::maxAllowed($userId) - self::existingQtyForDate($userId, $date, $excludeOrderId));
    }

    public static function exceedsDailyLimit(int $userId, string $date, int $requestedQty, ?int $excludeOrderId = null): bool
    {
        return $requestedQty > self::remainingQtyForDate($userId, $date, $excludeOrderId);
    }
}
