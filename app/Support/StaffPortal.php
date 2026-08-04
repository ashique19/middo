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

    /** @return list<string> */
    public static function moneyWriteRoles(): array
    {
        return ['admin', 'accounts'];
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

    /** Middo cash adjust, partner withdrawals, corporate wallet adjust. */
    public static function canWriteMoney(?string $role = null): bool
    {
        $role ??= Auth::user()?->role?->name;

        return in_array($role, self::moneyWriteRoles(), true);
    }

    /** Accept rider → Middo Due handovers (ops and accounts). */
    public static function canAcceptHandover(?string $role = null): bool
    {
        $role ??= Auth::user()?->role?->name;

        return in_array($role, ['admin', 'operation', 'accounts'], true);
    }

    /** Propose reject on a Middo Due handover (ops; admin break-glass). */
    public static function canProposeHandoverReject(?string $role = null): bool
    {
        $role ??= Auth::user()?->role?->name;

        return in_array($role, ['admin', 'operation'], true);
    }

    /** Confirm (or dismiss) a proposed Middo Due reject (accounts; admin break-glass). */
    public static function canConfirmHandoverReject(?string $role = null): bool
    {
        $role ??= Auth::user()?->role?->name;

        return in_array($role, ['admin', 'accounts'], true);
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
