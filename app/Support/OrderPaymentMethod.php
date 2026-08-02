<?php

namespace App\Support;

use App\Models\Order;
use InvalidArgumentException;

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

    /**
     * @return list<string>
     */
    public static function checkoutOptions(bool $prepaymentRequired, int $activeDateCount): array
    {
        if ($prepaymentRequired) {
            return [self::BALANCE, self::GATEWAY];
        }

        if (self::allowsCashOnDelivery(false, $activeDateCount)) {
            return self::all();
        }

        return [self::CASH_ON_DELIVERY];
    }

    public static function resolveCheckout(?string $requested, bool $prepaymentRequired, int $activeDateCount): string
    {
        $requested = $requested !== '' ? $requested : null;

        if ($prepaymentRequired) {
            if (in_array($requested, [self::BALANCE, self::GATEWAY], true)) {
                return $requested;
            }

            throw new InvalidArgumentException('Prepayment is required. Pay from Middo Balance or payment gateway.');
        }

        if (self::allowsCashOnDelivery(false, $activeDateCount)) {
            if ($requested === null) {
                return self::CASH_ON_DELIVERY;
            }

            if (in_array($requested, self::all(), true)) {
                return $requested;
            }

            throw new InvalidArgumentException('Choose Cash on Delivery, Middo Balance, or online payment.');
        }

        // Product policy: multi-date carts without forced prepay settle each day by COD.
        return self::CASH_ON_DELIVERY;
    }

    public static function checkoutChargeAmount(
        string $paymentMethod,
        bool $prepaymentRequired,
        int $prepaymentAmount,
        int $cartTotal
    ): int {
        if ($paymentMethod === self::CASH_ON_DELIVERY) {
            return 0;
        }

        return $prepaymentRequired ? max(0, $prepaymentAmount) : max(0, $cartTotal);
    }
}
