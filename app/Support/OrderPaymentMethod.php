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
     * COD is offered when prepayment is not required — i.e. projected active
     * orders stay below MiddoSettings::fullPrepayFromActiveOrders() and the
     * receiver matches the buyer profile.
     *
     * @param  int  $activeDateCount  Kept for call-site compatibility; COD gating
     *                                is driven by $prepaymentRequired.
     */
    public static function allowsCashOnDelivery(bool $prepaymentRequired, int $activeDateCount = 0): bool
    {
        return ! $prepaymentRequired;
    }

    /**
     * Whether Middo Balance can be selected for this checkout charge.
     */
    public static function balanceSelectable(int $walletBalance, int $chargeAmount): bool
    {
        if ($chargeAmount < 1) {
            // Choosing balance with a zero charge is pointless; still require funds on hand.
            return $walletBalance >= 1;
        }

        return $walletBalance >= $chargeAmount;
    }

    /**
     * @return list<string>
     */
    public static function checkoutOptions(
        bool $prepaymentRequired,
        int $activeDateCount,
        ?int $walletBalance = null,
        ?int $balanceChargeAmount = null
    ): array {
        if ($prepaymentRequired) {
            $options = [self::BALANCE, self::GATEWAY];
        } else {
            // COD + prepaid alternatives while under the active-order full-prepay ceiling.
            $options = [self::CASH_ON_DELIVERY, self::BALANCE, self::GATEWAY];
        }

        if ($walletBalance !== null) {
            $charge = $balanceChargeAmount ?? 1;
            if (! self::balanceSelectable($walletBalance, $charge)) {
                $options = array_values(array_filter(
                    $options,
                    fn (string $method) => $method !== self::BALANCE
                ));
            }
        }

        return $options;
    }

    public static function resolveCheckout(
        ?string $requested,
        bool $prepaymentRequired,
        int $activeDateCount,
        ?int $walletBalance = null,
        ?int $balanceChargeAmount = null
    ): string {
        $requested = $requested !== '' ? $requested : null;
        $options = self::checkoutOptions(
            $prepaymentRequired,
            $activeDateCount,
            $walletBalance,
            $balanceChargeAmount
        );

        if ($requested !== null && in_array($requested, $options, true)) {
            return $requested;
        }

        if ($prepaymentRequired) {
            if ($requested === self::BALANCE) {
                throw new InvalidArgumentException('Insufficient Middo Balance for this payment.');
            }

            throw new InvalidArgumentException('Prepayment is required. Pay from Middo Balance or payment gateway.');
        }

        if ($requested === self::BALANCE) {
            throw new InvalidArgumentException('Insufficient Middo Balance for this payment.');
        }

        if ($requested === null) {
            return $options[0] ?? self::CASH_ON_DELIVERY;
        }

        if (in_array($requested, self::all(), true) && ! in_array($requested, $options, true)) {
            throw new InvalidArgumentException('That payment method is not available for this order.');
        }

        throw new InvalidArgumentException('Choose Cash on Delivery, Middo Balance, or online payment.');
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
