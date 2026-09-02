<?php

namespace Tests\Support;

use App\Models\Order;
use App\Models\OrderMoneyEvent;
use App\Models\PartnerPayable;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\MiddoCashLedger;
use App\Support\OrderMoneyFlow;
use App\Support\RiderAccountLedger;
use PHPUnit\Framework\Assert;

/**
 * Shared oracles for process-flow money checks across roles.
 */
class MoneyFlowAssertions
{
    /**
     * @param  array{
     *   corporate_balance?: int,
     *   kitchen_wallet?: int,
     *   rider_wallet?: int,
     *   rider_ledger?: int,
     *   middo_cash?: int,
     *   amount_due?: int,
     *   amount_paid?: int,
     *   cash_collected?: int,
     *   cash_due_to_middo?: int,
     *   kitchen_share?: int,
     *   delivery_share?: int,
     *   middo_rest?: int,
     *   vat?: int,
     *   food?: int,
     * }  $expected
     */
    public static function assertRoleBalances(
        Order $order,
        ?User $corporate,
        ?User $kitchen,
        ?User $rider,
        array $expected,
        string $step
    ): void {
        $order->refresh();
        $prefix = "[{$step}] ";

        if (array_key_exists('corporate_balance', $expected) && $corporate) {
            Assert::assertSame(
                $expected['corporate_balance'],
                (int) $corporate->fresh()->balance,
                $prefix.'corporate Middo Balance'
            );
        }

        if (array_key_exists('kitchen_wallet', $expected) && $kitchen) {
            Assert::assertSame(
                $expected['kitchen_wallet'],
                KitchenAccountLedger::balance((int) $kitchen->id),
                $prefix.'kitchen wallet ledger'
            );
        }

        if (array_key_exists('rider_wallet', $expected) && $rider) {
            Assert::assertSame(
                $expected['rider_wallet'],
                (int) $rider->fresh()->balance,
                $prefix.'rider cash Due (users.balance)'
            );
        }

        if (array_key_exists('rider_ledger', $expected) && $rider) {
            Assert::assertSame(
                $expected['rider_ledger'],
                RiderAccountLedger::balance((int) $rider->id),
                $prefix.'rider commission ledger'
            );
        }

        if (array_key_exists('middo_cash', $expected)) {
            Assert::assertSame(
                $expected['middo_cash'],
                MiddoCashLedger::balance(),
                $prefix.'Middo cash till'
            );
        }

        if (array_key_exists('amount_due', $expected)) {
            Assert::assertSame($expected['amount_due'], $order->amountDue(), $prefix.'order amountDue()');
        }

        if (array_key_exists('amount_paid', $expected)) {
            Assert::assertSame($expected['amount_paid'], (int) $order->amount_paid, $prefix.'amount_paid');
        }

        if (array_key_exists('cash_collected', $expected)) {
            Assert::assertSame(
                $expected['cash_collected'],
                (int) ($order->cash_collected ?? 0),
                $prefix.'cash_collected'
            );
        }

        if (array_key_exists('cash_due_to_middo', $expected)) {
            Assert::assertSame(
                $expected['cash_due_to_middo'],
                (int) ($order->cash_due_to_middo ?? 0),
                $prefix.'cash_due_to_middo'
            );
        }

        if (array_key_exists('kitchen_share', $expected)) {
            Assert::assertSame(
                $expected['kitchen_share'],
                (int) ($order->kitchen_share_amount ?? 0),
                $prefix.'kitchen_share_amount'
            );
        }

        if (array_key_exists('delivery_share', $expected)) {
            Assert::assertSame(
                $expected['delivery_share'],
                (int) ($order->delivery_share_amount ?? 0),
                $prefix.'delivery_share_amount'
            );
        }

        if (array_key_exists('middo_rest', $expected)) {
            Assert::assertSame(
                $expected['middo_rest'],
                (int) ($order->middo_rest_amount ?? 0),
                $prefix.'middo_rest_amount'
            );
        }

        if (array_key_exists('vat', $expected)) {
            Assert::assertSame($expected['vat'], (int) ($order->vat_amount ?? 0), $prefix.'vat_amount');
        }

        if (array_key_exists('food', $expected)) {
            Assert::assertSame($expected['food'], (int) ($order->food_amount ?? 0), $prefix.'food_amount');
        }
    }

    /**
     * @param  list<string>  $types
     */
    public static function assertEventTypesExist(Order $order, array $types, string $step): void
    {
        foreach ($types as $type) {
            Assert::assertTrue(
                OrderMoneyEvent::query()
                    ->where('order_id', $order->id)
                    ->where('event_type', $type)
                    ->exists(),
                "[{$step}] missing money event type {$type}"
            );
        }
    }

    public static function assertEventCount(Order $order, string $type, int $count, string $step): void
    {
        Assert::assertSame(
            $count,
            OrderMoneyEvent::query()
                ->where('order_id', $order->id)
                ->where('event_type', $type)
                ->count(),
            "[{$step}] event count for {$type}"
        );
    }

    public static function assertPayable(
        Order $order,
        string $role,
        int $amount,
        string $status,
        string $step
    ): void {
        $exists = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', $role)
            ->where('amount', $amount)
            ->where('status', $status)
            ->exists();

        Assert::assertTrue($exists, "[{$step}] partner payable {$role} amount={$amount} status={$status}");
    }

    /**
     * @param  array<string, int|float|string|null>  $summary
     */
    public static function assertTreeSummary(Order $order, array $summary, string $step): void
    {
        $tree = OrderMoneyFlow::treeForOrder(
            $order->fresh(['moneyEvents', 'partnerPayables', 'menuItem'])
        );

        foreach ($summary as $key => $value) {
            Assert::assertSame(
                $value,
                $tree['summary'][$key] ?? null,
                "[{$step}] tree summary.{$key}"
            );
        }
    }

    /**
     * Hand-compute inclusive-VAT food split using current settings (default 5%).
     *
     * @return array{food: int, vat: int, food_ex_vat: int, kitchen: int, delivery: int, middo_rest: int, bill_net: int}
     */
    public static function expectedBreakdown(
        int $unitPrice,
        int $qty,
        int $kitchenUnit,
        int $deliveryUnit,
        float $vatRatePct = 5.0,
        int $charges = 0,
        int $discount = 0,
        int $deliveryVat = 0
    ): array {
        $food = $unitPrice * $qty;
        $foodEx = $vatRatePct > 0 ? (int) round($food / (1 + ($vatRatePct / 100))) : $food;
        $vat = max(0, $food - $foodEx);
        $kitchen = $kitchenUnit * $qty;
        $delivery = $deliveryUnit * $qty;
        $billNet = max(0, $food + $charges - $discount);
        $partnerTotal = $kitchen + $delivery;
        if ($partnerTotal > $billNet && $partnerTotal > 0) {
            $kitchen = (int) floor(($kitchen * $billNet) / $partnerTotal);
            $delivery = $billNet - $kitchen;
        }
        $middoRest = max(0, $billNet - $vat - $deliveryVat - $kitchen - $delivery);

        return [
            'food' => $food,
            'vat' => $vat,
            'food_ex_vat' => $foodEx,
            'kitchen' => $kitchen,
            'delivery' => $delivery,
            'middo_rest' => $middoRest,
            'bill_net' => $billNet,
        ];
    }

    public static function assertOpenPayablesCount(Order $order, int $count, string $step): void
    {
        Assert::assertSame(
            $count,
            PartnerPayable::query()
                ->where('order_id', $order->id)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->count(),
            "[{$step}] open partner payables"
        );
    }
}
