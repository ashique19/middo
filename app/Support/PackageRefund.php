<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PackageSubscription;

class PackageRefund
{
    public static function subscriptionNetTotal(PackageSubscription $subscription): int
    {
        return max(
            0,
            (int) $subscription->total_amount
            - (int) ($subscription->discount_amount ?? 0)
            + (int) ($subscription->charges_amount ?? 0)
        );
    }

    public static function subscriptionPrepaidAmount(PackageSubscription $subscription): int
    {
        $netTotal = self::subscriptionNetTotal($subscription);
        $paid = (int) ($subscription->amount_paid ?? 0);

        if ($paid > 0) {
            return min($paid, $netTotal);
        }

        return $netTotal;
    }

    public static function orderRefundAmount(Order $order): int
    {
        if ($order->package_subscription_id) {
            return self::packageDayRefundAmount($order);
        }

        $netTotal = $order->netTotalAmount();
        $paid = (int) ($order->amount_paid ?? 0);

        if ($paid > 0) {
            return min($paid, $netTotal);
        }

        return $netTotal;
    }

    public static function packageDayRefundAmount(Order $order): int
    {
        $subscription = $order->packageSubscription;

        if (! $subscription && $order->package_subscription_id) {
            $subscription = PackageSubscription::query()
                ->with('orders')
                ->find($order->package_subscription_id);
        }

        if (! $subscription) {
            return min((int) ($order->amount_paid ?: $order->total_amount), $order->netTotalAmount());
        }

        $allocations = self::packageDayRefundAllocations($subscription);
        if (array_key_exists((int) $order->id, $allocations)) {
            return $allocations[(int) $order->id];
        }

        return min((int) ($order->amount_paid ?: $order->total_amount), $order->netTotalAmount());
    }

    /**
     * @return array<int, int>
     */
    public static function packageDayRefundAllocations(PackageSubscription $subscription): array
    {
        $orders = $subscription->orders()
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get(['id']);

        $count = $orders->count();
        if ($count < 1) {
            return [];
        }

        $refundable = self::subscriptionPrepaidAmount($subscription);
        if ($refundable <= 0) {
            return $orders->mapWithKeys(fn (Order $order) => [(int) $order->id => 0])->all();
        }

        $base = intdiv($refundable, $count);
        $remainder = $refundable % $count;

        return $orders
            ->values()
            ->mapWithKeys(fn (Order $order, int $index) => [
                (int) $order->id => $base + ($index < $remainder ? 1 : 0),
            ])
            ->all();
    }
}
