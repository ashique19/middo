<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use Carbon\Carbon;

class KitchenAcceptWindow
{
    public static function scheduledStart(OrderGroup $group): Carbon
    {
        $group->loadMissing('orders');

        $starts = $group->orders
            ->filter(fn (Order $order) => $order->order_status !== 'cancelled')
            ->map(function (Order $order) {
                return Carbon::parse(
                    $order->delivery_date->toDateString().' '.$order->delivery_time,
                    'Asia/Dhaka'
                );
            })
            ->sort()
            ->values();

        if ($starts->isNotEmpty()) {
            return $starts->first();
        }

        return Carbon::parse($group->delivery_date->toDateString().' 12:00 PM', 'Asia/Dhaka');
    }

    /**
     * Inclusive accept window for a group.
     *
     * - No start clock: [delivery − minutes, delivery]
     * - Start clock before delivery on delivery day: [that clock, delivery]
     * - Start clock at/after delivery (e.g. 10:30 PM for lunch): night-before
     *   window [previous day @ start, open + minutes], capped by delivery.
     *
     * Live MiddoSettings always apply — never frozen at order/group creation.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function windowBounds(OrderGroup $group): array
    {
        $delivery = self::scheduledStart($group);
        $minutes = MiddoSettings::acceptWindowMinutes();

        $startsAt = MiddoSettings::acceptWindowStartsAt();
        if ($startsAt === null) {
            return [
                $delivery->copy()->subMinutes($minutes),
                $delivery->copy(),
            ];
        }

        $onDeliveryDay = Carbon::parse(
            $delivery->toDateString().' '.$startsAt,
            'Asia/Dhaka'
        );

        // Same-day morning/afternoon start — open until delivery.
        if ($onDeliveryDay->lt($delivery)) {
            return [$onDeliveryDay, $delivery->copy()];
        }

        // Evening (or otherwise at/after delivery) → open the previous calendar
        // day at the configured clock for `accept_window_minutes`.
        $openAt = $onDeliveryDay->copy()->subDay();
        $closeAt = $openAt->copy()->addMinutes($minutes);
        if ($closeAt->gt($delivery)) {
            $closeAt = $delivery->copy();
        }

        return [$openAt, $closeAt];
    }

    public static function windowOpenAt(OrderGroup $group): Carbon
    {
        return self::windowBounds($group)[0];
    }

    public static function windowCloseAt(OrderGroup $group): Carbon
    {
        return self::windowBounds($group)[1];
    }

    public static function minutesUntilClose(OrderGroup $group, ?Carbon $now = null): int
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        $closeAt = self::windowCloseAt($group);

        if ($now->gte($closeAt)) {
            return 0;
        }

        return (int) max(0, $now->diffInMinutes($closeAt));
    }

    public static function isClosingSoon(OrderGroup $group, ?Carbon $now = null, ?int $warnMinutes = null): bool
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        $warnMinutes = $warnMinutes ?? MiddoSettings::acceptWindowWarnMinutes();

        if (! self::isOpen($group, $now)) {
            return false;
        }

        return self::minutesUntilClose($group, $now) <= $warnMinutes;
    }

    public static function isOpen(OrderGroup $group, ?Carbon $now = null): bool
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        [$openAt, $closeAt] = self::windowBounds($group);

        return $now->betweenIncluded($openAt, $closeAt);
    }

    public static function assertCanAccept(OrderGroup $group, ?Carbon $now = null): void
    {
        if (self::isOpen($group, $now)) {
            return;
        }

        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        [$openAt, $closeAt] = self::windowBounds($group);

        if ($now->lt($openAt)) {
            throw new \RuntimeException(sprintf(
                'Accept window opens at %s (Asia/Dhaka).',
                $openAt->format('g:i A')
            ));
        }

        throw new \RuntimeException(sprintf(
            'Accept window closed at %s (Asia/Dhaka).',
            $closeAt->format('g:i A')
        ));
    }

    /**
     * @return array{
     *     is_open: bool,
     *     state: string,
     *     label: string,
     *     open_at_iso: string,
     *     close_at_iso: string,
     *     closing_soon: bool,
     *     minutes_remaining: int|null
     * }
     */
    public static function statusPayload(OrderGroup $group, ?Carbon $now = null): array
    {
        $now = ($now ?? now('Asia/Dhaka'))->copy()->timezone('Asia/Dhaka');
        [$openAt, $closeAt] = self::windowBounds($group);
        $isOpen = $now->betweenIncluded($openAt, $closeAt);
        $minutesRemaining = $isOpen ? self::minutesUntilClose($group, $now) : null;
        $closingSoon = $isOpen && self::isClosingSoon($group, $now);

        if ($isOpen) {
            $state = 'open';
            $label = $closingSoon
                ? 'Closing in '.$minutesRemaining.'m'
                : 'Accept by '.$closeAt->format('g:i A');
        } elseif ($now->lt($openAt)) {
            $state = 'not_yet';
            $label = 'Opens '.$openAt->format('g:i A');
        } else {
            $state = 'closed';
            $label = 'Window closed';
        }

        return [
            'is_open' => $isOpen,
            'state' => $state,
            'label' => $label,
            'open_at_iso' => $openAt->toIso8601String(),
            'close_at_iso' => $closeAt->toIso8601String(),
            'closing_soon' => $closingSoon,
            'minutes_remaining' => $minutesRemaining,
        ];
    }
}
