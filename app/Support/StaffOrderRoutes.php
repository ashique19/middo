<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class StaffOrderRoutes
{
    public static function prefix(): string
    {
        return StaffPortal::rolePrefix(Auth::user()?->role?->name);
    }

    public static function show(Order|int $order, ?string $lens = null): string
    {
        $url = route(self::prefix().'.orders.show', $order);

        if ($lens !== null && $lens !== '') {
            $url .= '?lens='.urlencode(OrderLens::normalize($lens));
        }

        return $url;
    }
}
