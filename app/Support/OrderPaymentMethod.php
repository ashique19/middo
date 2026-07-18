<?php

namespace App\Support;

use App\Models\Order;

class OrderPaymentMethod
{
    public const BALANCE = 'balance';

    public const GATEWAY = 'gateway';

    public const CASH_ON_DELIVERY = 'cash_on_delivery';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::BALANCE, self::GATEWAY, self::CASH_ON_DELIVERY];
    }

    public static function label(?string $method): string
    {
        return match ($method) {
            self::BALANCE => 'Middo Balance',
            self::GATEWAY => 'Online payment',
            self::CASH_ON_DELIVERY => 'Cash on Delivery',
            default => '—',
        };
    }

    /**
     * Resolve a stored or inferred payment method for display.
     */
    public static function resolve(Order $order): ?string
    {
        $stored = $order->payment_method;
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        // Legacy unpaid residual → COD at delivery.
        if ($order->amountDue() > 0) {
            return self::CASH_ON_DELIVERY;
        }

        if ($order->prepaidAmountValue() > 0 || $order->amountPaidValue() > 0) {
            return self::BALANCE;
        }

        return null;
    }

    public static function labelForOrder(Order $order): string
    {
        return self::label(self::resolve($order));
    }

    /**
     * COD is only offered when the cart is a single order line and prepayment is not required.
     */
    public static function allowsCashOnDelivery(bool $prepaymentRequired, int $activeDateCount): bool
    {
        return ! $prepaymentRequired && $activeDateCount === 1;
    }
}
