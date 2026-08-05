<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

/**
 * Rider-side SLA for packed kitchen-dispatched orders awaiting accept (N2).
 *
 * Mirrors kitchen accept-window close (= delivery wall-clock) and dispatch
 * deadline pressure: age from dispatched_at; claim-by = delivery datetime;
 * "should be on the way" hint uses DispatchDeadline (delivery − buffer).
 */
class RiderAcceptSla
{
    public static function deliveryAt(Order $order): Carbon
    {
        return Carbon::parse(
            $order->delivery_date->toDateString().' '.$order->delivery_time,
            'Asia/Dhaka'
        );
    }

    /**
     * When the rider should have claimed so the run can still meet delivery.
     * Same wall-clock as kitchen accept-window close.
     */
    public static function claimBy(Order $order): Carbon
    {
        return self::deliveryAt($order);
    }

    /**
     * Soft pressure deadline (same as kitchen pack deadline).
     */
    public static function onTheWayBy(Order $order): Carbon
    {
        return DispatchDeadline::forOrder($order);
    }

    public static function minutesWaiting(Order $order, ?Carbon $now = null): int
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy();
        if (! $order->dispatched_at) {
            return 0;
        }

        $dispatched = $order->dispatched_at instanceof \DateTimeInterface
            ? Carbon::instance($order->dispatched_at)
            : Carbon::parse($order->dispatched_at);

        if ($now->lte($dispatched)) {
            return 0;
        }

        return (int) max(0, abs($dispatched->diffInMinutes($now)));
    }

    public static function minutesToClaimBy(Order $order, ?Carbon $now = null): int
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        $claimBy = self::claimBy($order);
        if ($now->gte($claimBy)) {
            return 0;
        }

        return (int) max(0, $now->diffInMinutes($claimBy));
    }

    public static function ageWarnMinutes(): int
    {
        return MiddoSettings::riderUnclaimedAgeWarnMinutes();
    }

    /**
     * @return array{
     *     state: string,
     *     label: string,
     *     minutes_waiting: int,
     *     minutes_to_claim: int|null,
     *     closing_soon: bool,
     *     aging: bool,
     *     overdue: bool,
     *     claim_by_iso: string,
     *     on_the_way_by_iso: string,
     *     dispatched_at_iso: string|null,
     *     priority: int
     * }
     */
    public static function statusPayload(Order $order, ?Carbon $now = null): array
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        $claimBy = self::claimBy($order);
        $onTheWayBy = self::onTheWayBy($order);
        $waiting = self::minutesWaiting($order, $now);
        $toClaim = self::minutesToClaimBy($order, $now);
        $overdue = $now->gte($claimBy);
        $pastOnTheWay = $now->gte($onTheWayBy);
        $closingSoon = ! $overdue && $toClaim <= MiddoSettings::acceptWindowWarnMinutes();
        $aging = ! $overdue && $waiting >= self::ageWarnMinutes();

        if ($overdue) {
            $state = 'overdue';
            $label = 'Past delivery '.$claimBy->format('g:i A').' · waiting '.$waiting.'m';
            $priority = 0;
        } elseif ($pastOnTheWay || $closingSoon) {
            $state = 'closing_soon';
            $label = $pastOnTheWay
                ? 'Past on-the-way-by '.$onTheWayBy->format('g:i A').' · '.$toClaim.'m to delivery'
                : 'Claim in '.$toClaim.'m · waiting '.$waiting.'m';
            $priority = 1;
        } elseif ($aging) {
            $state = 'aging';
            $label = 'Unclaimed '.$waiting.'m · claim by '.$claimBy->format('g:i A');
            $priority = 2;
        } else {
            $state = 'ok';
            $label = 'Waiting '.$waiting.'m · claim by '.$claimBy->format('g:i A');
            $priority = 3;
        }

        return [
            'state' => $state,
            'label' => $label,
            'minutes_waiting' => $waiting,
            'minutes_to_claim' => $overdue ? null : $toClaim,
            'closing_soon' => $closingSoon || $pastOnTheWay,
            'aging' => $aging || $closingSoon || $pastOnTheWay || $overdue,
            'overdue' => $overdue,
            'claim_by_iso' => $claimBy->toIso8601String(),
            'on_the_way_by_iso' => $onTheWayBy->toIso8601String(),
            'dispatched_at_iso' => $order->dispatched_at
                ? Carbon::instance($order->dispatched_at instanceof \DateTimeInterface
                    ? $order->dispatched_at
                    : Carbon::parse($order->dispatched_at)
                )->timezone('Asia/Dhaka')->toIso8601String()
                : null,
            'priority' => $priority,
        ];
    }
}
