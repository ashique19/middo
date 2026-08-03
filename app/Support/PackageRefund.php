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

    /**
     * Confirmed package days store menu×qty on amount_paid. Refund that food line
     * plus a pro-rated share of subscription charges/discount across billable_days.
     */
    public static function packageDayRefundAmount(Order $order): int
    {
        $subscription = $order->packageSubscription;

        if (! $subscription && $order->package_subscription_id) {
            $subscription = PackageSubscription::query()->find($order->package_subscription_id);
        }

        $dayFood = min(
            (int) ($order->amount_paid ?: $order->total_amount),
            max(0, (int) $order->total_amount - (int) ($order->discount_amount ?? 0))
        );

        if (! $subscription) {
            return $dayFood;
        }

        $allocations = self::packageDayRefundAllocations($subscription);
        if (array_key_exists((int) $order->id, $allocations)) {
            return $allocations[(int) $order->id];
        }

        $days = max(1, (int) $subscription->billable_days);
        $chargeShare = (int) floor(((int) ($subscription->charges_amount ?? 0)) / $days);
        $discountShare = (int) floor(((int) ($subscription->discount_amount ?? 0)) / $days);

        return max(0, $dayFood + $chargeShare - $discountShare);
    }

    /**
     * @return array<int, int>
     */
    public static function packageDayRefundAllocations(PackageSubscription $subscription): array
    {
        $orders = $subscription->orders()
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get(['id', 'amount_paid', 'total_amount', 'discount_amount']);

        if ($orders->isEmpty()) {
            return [];
        }

        $days = max(1, (int) $subscription->billable_days);
        $charges = (int) ($subscription->charges_amount ?? 0);
        $discount = (int) ($subscription->discount_amount ?? 0);
        $chargeBase = intdiv($charges, $days);
        $chargeRem = $charges % $days;
        $discountBase = intdiv($discount, $days);
        $discountRem = $discount % $days;

        return $orders
            ->values()
            ->mapWithKeys(function (Order $order, int $index) use ($chargeBase, $chargeRem, $discountBase, $discountRem) {
                $dayFood = (int) ($order->amount_paid ?: $order->total_amount);
                $chargeShare = $chargeBase + ($index < $chargeRem ? 1 : 0);
                $discountShare = $discountBase + ($index < $discountRem ? 1 : 0);

                return [(int) $order->id => max(0, $dayFood + $chargeShare - $discountShare)];
            })
            ->all();
    }
}
