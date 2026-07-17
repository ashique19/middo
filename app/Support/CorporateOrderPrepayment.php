<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

class CorporateOrderPrepayment
{
    /** Active order count above this requires 50% prepayment. */
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
     * @param  list<array{date?: string, quantity?: int}|int>  $datesOrCount
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
        $reasons = [];

        $mismatch = ! self::profileMatchesReceiver($user, $receiverName, $mobile);
        if ($mismatch) {
            $reasons[] = 'receiver_mismatch';
        }

        if ($projected > self::ACTIVE_ORDER_PREPAY_THRESHOLD) {
            $reasons[] = 'active_order_limit';
        }

        $ratio = 0.0;
        if (in_array('receiver_mismatch', $reasons, true)) {
            $ratio = 1.0;
        } elseif (in_array('active_order_limit', $reasons, true)) {
            $ratio = 0.5;
        }

        $amount = (int) round($cartTotal * $ratio);
        $balance = (int) $user->balance;
        $reason = $reasons[0] ?? null;

        $message = null;
        if ($ratio >= 1.0) {
            $message = 'This meal is for a different worker than your Middo buyer profile (name or mobile). Full prepayment is required via Middo Balance or payment gateway.';
        } elseif ($ratio >= 0.5) {
            $message = sprintf(
                'You would have %d active orders (limit %d without prepayment). 50%% prepayment (৳%s) is required via Middo Balance or payment gateway.',
                $projected,
                self::ACTIVE_ORDER_PREPAY_THRESHOLD,
                number_format($amount)
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
