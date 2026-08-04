<?php

namespace App\Support;

class OrderStatusPushCopy
{
    /**
     * @return array{title: string, body: string}|null
     */
    public static function forStatus(string $status, int $orderId, ?string $mealName = null): ?array
    {
        $meal = $mealName ? " · {$mealName}" : '';

        return match ($status) {
            'processing' => [
                'title' => 'Kitchen accepted your order',
                'body' => "Order #{$orderId}{$meal} is being prepared.",
            ],
            'ready' => [
                'title' => 'Kitchen finished prep',
                'body' => "Order #{$orderId}{$meal} is ready and waiting for rider pickup.",
            ],
            'packed' => [
                'title' => 'Your order is packed',
                'body' => "Order #{$orderId}{$meal} is packed and handed to delivery.",
            ],
            'on_the_way_to_delivery' => [
                'title' => 'Your order is on the way',
                'body' => "Order #{$orderId}{$meal} has been dispatched for delivery.",
            ],
            'delivered' => [
                'title' => 'Order delivered',
                'body' => "Order #{$orderId}{$meal} was delivered. Enjoy your lunch!",
            ],
            'delivered_and_paid' => [
                'title' => 'Delivery complete',
                'body' => "Order #{$orderId}{$meal} is delivered and paid. Thank you!",
            ],
            'cancelled' => [
                'title' => 'Order cancelled',
                'body' => "Order #{$orderId}{$meal} was cancelled.",
            ],
            default => null,
        };
    }
}
