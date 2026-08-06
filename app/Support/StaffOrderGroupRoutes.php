<?php

namespace App\Support;

use App\Models\OrderGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class StaffOrderGroupRoutes
{
    public static function prefix(): ?string
    {
        return match (Auth::user()?->role?->name) {
            'admin' => 'admin',
            'operation' => 'operation',
            default => null,
        };
    }

    public static function show(OrderGroup|int $group): ?string
    {
        $prefix = self::prefix();
        if ($prefix === null) {
            return null;
        }

        $name = $prefix.'.order-groups.show';

        return Route::has($name) ? route($name, $group) : null;
    }
}
