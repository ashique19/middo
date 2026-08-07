<?php

namespace App\Support;

use App\Models\Order;
use RuntimeException;

class OrderTransition
{
    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const RIDER_ASSIGNED = 'rider_assigned';

    public const PACKED = 'packed';

    public const ON_THE_WAY_TO_DELIVERY = 'on_the_way_to_delivery';

    public const DELIVERED = 'delivered';

    public const DELIVERED_AND_PAID = 'delivered_and_paid';

    public const CANCELLED = 'cancelled';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'pending' => [
            self::PROCESSING,
            self::CANCELLED,
        ],
        self::PROCESSING => [
            self::READY,
        ],
        self::READY => [
            self::RIDER_ASSIGNED,
        ],
        self::RIDER_ASSIGNED => [
            self::PACKED,
            self::READY, // kitchen/ops release claim
        ],
        self::PACKED => [
            self::ON_THE_WAY_TO_DELIVERY,
        ],
        self::ON_THE_WAY_TO_DELIVERY => [
            self::DELIVERED,
            self::DELIVERED_AND_PAID,
        ],
        self::DELIVERED => [
            self::DELIVERED_AND_PAID,
        ],
    ];

    public static function can(Order $order, string $to): bool
    {
        return in_array($to, self::ALLOWED[(string) $order->order_status] ?? [], true);
    }

    public static function assertCan(Order $order, string $to): void
    {
        if (self::can($order, $to)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Order #%d cannot move from %s to %s.',
            (int) $order->id,
            (string) $order->order_status,
            $to
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function apply(Order $order, string $to, array $attributes = []): void
    {
        self::assertCan($order, $to);

        $order->update([
            ...$attributes,
            'order_status' => $to,
        ]);
    }
}
