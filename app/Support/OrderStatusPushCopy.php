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
            'on_the_way_to_delivery' => [
                'title' => 'Your order is on the way',
                'body' => "Order #{$orderId}{$meal} has been dispatched for delivery.",
            ],
            'delivered', 'delivered_and_paid' => [
                'title' => 'Order delivered',
                'body' => "Order #{$orderId}{$meal} was delivered. Enjoy your lunch!",
            ],
            'cancelled' => [
                'title' => 'Order cancelled',
                'body' => "Order #{$orderId}{$meal} was cancelled.",
            ],
            default => null,
        };
    }
}
