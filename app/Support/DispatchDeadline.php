<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

class DispatchDeadline
{
    public static function forOrder(Order $order): Carbon
    {
        $minutesBefore = (int) config('middo.dispatch_deadline_minutes_before', 60);

        return Carbon::parse(
            $order->delivery_date->toDateString().' '.$order->delivery_time,
            'Asia/Dhaka'
        )->subMinutes($minutesBefore);
    }

    public static function earliestForOrders(iterable $orders): ?Carbon
    {
        $deadlines = collect($orders)
            ->map(fn (Order $order) => static::forOrder($order))
            ->sort()
            ->values();

        return $deadlines->first();
    }
}
