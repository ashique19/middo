<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Shared helpers for admin / operation / accounts staff portals.
 */
class StaffPortal
{
    /** @return list<string> */
    public static function moneyRoles(): array
    {
        return ['admin', 'operation', 'accounts'];
    }

    /** @return list<string> */
    public static function dayOpsRoles(): array
    {
        return ['admin', 'operation'];
    }

    public static function canAccessMoney(?string $role = null): bool
    {
        $role ??= Auth::user()?->role?->name;

        return in_array($role, self::moneyRoles(), true);
    }

    public static function isDayOps(?string $role = null): bool
    {
        $role ??= Auth::user()?->role?->name;

        return in_array($role, self::dayOpsRoles(), true);
    }

    /**
     * Route name prefix for the current (or given) staff role.
     */
    public static function rolePrefix(?string $role = null): string
    {
        $role ??= Auth::user()?->role?->name;

        return match ($role) {
            'admin' => 'admin',
            'accounts' => 'accounts',
            default => 'operation',
        };
    }
}
