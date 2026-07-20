<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class StaffOrderRoutes
{
    public static function prefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public static function show(Order|int $order): string
    {
        return route(self::prefix().'.orders.show', $order);
    }
}
