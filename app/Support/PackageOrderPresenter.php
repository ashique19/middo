<?php

namespace App\Support;

use App\Models\Order;

class PackageOrderPresenter
{
    /**
     * @return array{
     *     is_package: bool,
     *     package_subscription_id: int|null,
     *     package_name: string|null,
     *     package_badge: string|null
     * }
     */
    public static function fields(Order $order): array
    {
        $order->loadMissing('packageSubscription.package');

        $isPackage = $order->isPackageOrder();
        $packageName = $isPackage
            ? ($order->packageSubscription?->package?->name)
            : null;

        return [
            'is_package' => $isPackage,
            'package_subscription_id' => $order->package_subscription_id
                ? (int) $order->package_subscription_id
                : null,
            'package_name' => $packageName,
            'package_badge' => $isPackage ? 'Package' : null,
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>|iterable<int, Order>  $orders
     */
    public static function collectionHasPackage(iterable $orders): bool
    {
        foreach ($orders as $order) {
            if ($order instanceof Order) {
                if ($order->isPackageOrder()) {
                    return true;
                }

                continue;
            }

            if (is_array($order) && ! empty($order['is_package'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $orderNodes
     * @return string|null "package"|"mixed"|null
     */
    public static function groupSource(iterable $orderNodes): ?string
    {
        $hasPackage = false;
        $hasOther = false;

        foreach ($orderNodes as $order) {
            if (! empty($order['is_package'])) {
                $hasPackage = true;
            } else {
                $hasOther = true;
            }
        }

        if ($hasPackage && $hasOther) {
            return 'mixed';
        }

        if ($hasPackage) {
            return 'package';
        }

        return null;
    }
}
