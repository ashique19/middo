<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;

class OperationPermissions
{
    public const DASHBOARD = 'operation.dashboard';

    public const ALERTS = 'operation.alerts';

    public const BOXES = 'operation.boxes';

    public const RIDERS = 'operation.riders';

    public const CASH = 'operation.cash';

    public const SLA = 'operation.sla';

    public const COMPLAINTS = 'operation.complaints';

    public const ORDERS = 'operation.orders';

    public const PROFILE = 'operation.profile';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DASHBOARD,
            self::ALERTS,
            self::BOXES,
            self::RIDERS,
            self::CASH,
            self::SLA,
            self::COMPLAINTS,
            self::ORDERS,
            self::PROFILE,
        ];
    }

    /**
     * Permissions shipped in Phase 0 (auth + home + alerts).
     *
     * @return list<string>
     */
    public static function phaseZero(): array
    {
        return [
            self::DASHBOARD,
            self::ALERTS,
            self::PROFILE,
        ];
    }

    public static function syncOperationRole(?Role $operation = null): void
    {
        $operation ??= Role::query()->where('name', 'operation')->first();
        if (! $operation) {
            return;
        }

        $ids = [];
        foreach (self::all() as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name]);
            $ids[] = $permission->id;
        }

        $operation->permissions()->syncWithoutDetaching($ids);
    }
}
