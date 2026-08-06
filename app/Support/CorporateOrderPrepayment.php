<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

class CorporateOrderPrepayment
{
    /**
     * Default "full prepay from N active orders" when settings/config are unavailable.
     * Prefer MiddoSettings::fullPrepayFromActiveOrders().
     */
    public const ACTIVE_ORDER_PREPAY_THRESHOLD = 3;

    public static function normalizeName(?string $name): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', (string) $name) ?? ''));

        return $normalized;
    }

    public static function normalizeMobile(?string $mobile): string
    {
        return OrderConfirmationOtp::formatMobile((string) $mobile);
    }

    /**
     * Detect whether the Middo account holder and the meal receiver are the same person.
     * Compares normalized profile name + mobile to checkout receiver name + mobile.
     * If either differs, full prepayment is required (see evaluate()).
     */
    public static function profileMatchesReceiver(User $user, string $receiverName, string $mobile): bool
    {
        $profileName = self::normalizeName($user->name);
        $receiver = self::normalizeName($receiverName);

        if ($profileName === '' || $receiver === '' || $profileName !== $receiver) {
            return false;
        }

        return self::normalizeMobile($user->mobile) === self::normalizeMobile($mobile);
    }

    public static function activeOrderCount(int $userId): int
    {
        return (int) Order::query()
            ->where('user_id', $userId)
            ->active()
            ->count();
    }

    /**
     * Projected active orders at/above this count require 100% prepayment.
     */
    public static function fullPrepayFromActiveOrders(): int
    {
        return MiddoSettings::fullPrepayFromActiveOrders();
    }

    /**
     * @return array{
     *   required: bool,
     *   ratio: float,
     *   amount: int,
     *   cart_total: int,
     *   reason: string|null,
     *   reasons: list<string>,
     *   message: string|null,
     *   active_orders: int,
     *   new_orders: int,
     *   projected_active: int,
     *   full_prepay_from: int,
     *   balance: int,
     *   balance_sufficient: bool
     * }
     */
    public static function evaluate(
        User $user,
        string $receiverName,
        string $mobile,
        int $newOrderCount,
        int $cartTotal
    ): array {
        $active = self::activeOrderCount($user->id);
        $projected = $active + max(0, $newOrderCount);
        $fullPrepayFrom = self::fullPrepayFromActiveOrders();
        $reasons = [];

        $mismatch = ! self::profileMatchesReceiver($user, $receiverName, $mobile);
        if ($mismatch) {
            $reasons[] = 'receiver_mismatch';
        }

        if ($projected >= $fullPrepayFrom) {
            $reasons[] = 'active_order_limit';
        }

        $ratio = 0.0;
        if ($reasons !== []) {
            // Receiver mismatch and active-order ceiling both require full prepayment.
            $ratio = 1.0;
        }

        $amount = (int) round($cartTotal * $ratio);
        $balance = (int) $user->balance;
        $reason = $reasons[0] ?? null;

        $message = null;
        if (in_array('receiver_mismatch', $reasons, true)) {
            $message = 'This meal is for a different worker than your Middo buyer profile (name or mobile). Full prepayment is required via Middo Balance or payment gateway.';
        } elseif (in_array('active_order_limit', $reasons, true)) {
            $message = sprintf(
                'You would have %d active orders. Full prepayment (৳%s) is required from %d+ active orders via Middo Balance or payment gateway.',
                $projected,
                number_format($amount),
                $fullPrepayFrom
            );
        }

        return [
            'required' => $ratio > 0,
            'ratio' => $ratio,
            'amount' => $amount,
            'cart_total' => $cartTotal,
            'reason' => $reason,
            'reasons' => $reasons,
            'message' => $message,
            'active_orders' => $active,
            'new_orders' => $newOrderCount,
            'projected_active' => $projected,
            'full_prepay_from' => $fullPrepayFrom,
            'balance' => $balance,
            'balance_sufficient' => $balance >= $amount,
        ];
    }

    /**
     * Split a prepaid amount across order line totals (largest remainder method).
     *
     * @param  list<int>  $lineTotals
     * @return list<int>
     */
    public static function allocate(int $prepaidTotal, array $lineTotals): array
    {
        $count = count($lineTotals);
        if ($count === 0) {
            return [];
        }

        $cartTotal = array_sum($lineTotals);
        if ($cartTotal <= 0 || $prepaidTotal <= 0) {
            return array_fill(0, $count, 0);
        }

        $prepaidTotal = min($prepaidTotal, $cartTotal);
        $shares = [];
        $fractions = [];
        $assigned = 0;

        foreach ($lineTotals as $i => $lineTotal) {
            $exact = ($prepaidTotal * $lineTotal) / $cartTotal;
            $share = (int) floor($exact);
            $shares[$i] = min($share, $lineTotal);
            $fractions[$i] = $exact - $share;
            $assigned += $shares[$i];
        }

        $remaining = $prepaidTotal - $assigned;
        arsort($fractions);

        foreach (array_keys($fractions) as $i) {
            if ($remaining <= 0) {
                break;
            }
            if ($shares[$i] < $lineTotals[$i]) {
                $shares[$i]++;
                $remaining--;
            }
        }

        return array_values($shares);
    }
}
