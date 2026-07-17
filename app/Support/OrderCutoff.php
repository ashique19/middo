<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Corporate order / cancel / edit cutoff for a delivery calendar day (Asia/Dhaka).
 * After this time on the delivery date, same-day orders cannot be placed, edited, or cancelled.
 */
class OrderCutoff
{
    public static function timezone(): string
    {
        return (string) config('middo.order_cutoff_timezone', 'Asia/Dhaka');
    }

    public static function hour(): int
    {
        return (int) config('middo.order_cutoff_hour', 15);
    }

    public static function minute(): int
    {
        return (int) config('middo.order_cutoff_minute', 28);
    }

    public static function label(): string
    {
        return Carbon::createFromTime(self::hour(), self::minute(), 0, self::timezone())
            ->format('g:i A');
    }

    public static function forDate(CarbonInterface|string $deliveryDate): Carbon
    {
        $date = $deliveryDate instanceof CarbonInterface
            ? $deliveryDate->copy()->timezone(self::timezone())
            : Carbon::parse($deliveryDate, self::timezone());

        return $date->copy()->startOfDay()
            ->setTime(self::hour(), self::minute(), 0);
    }

    public static function isPastForDeliveryDate(CarbonInterface|string $deliveryDate, ?CarbonInterface $now = null): bool
    {
        $now = ($now ?? now(self::timezone()))->copy()->timezone(self::timezone());

        return $now->greaterThanOrEqualTo(self::forDate($deliveryDate));
    }

    /**
     * Pending orders may be edited/cancelled only before the delivery day's order cutoff.
     */
    public static function allowsModification(Order $order, ?CarbonInterface $now = null): bool
    {
        if ($order->order_status !== 'pending') {
            return false;
        }

        return ! self::isPastForDeliveryDate($order->delivery_date, $now);
    }

    public static function modificationDeniedMessage(): string
    {
        return 'Orders can only be changed or cancelled until the '.self::label().' order deadline on the delivery day.';
    }

    public static function placementDeniedMessage(string $deliveryDate): string
    {
        return sprintf(
            'Ordering for %s is closed after the %s deadline (Asia/Dhaka).',
            $deliveryDate,
            self::label()
        );
    }
}
