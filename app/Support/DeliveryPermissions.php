<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;

class DeliveryPermissions
{
    public const DASHBOARD = 'delivery.dashboard';

    public const ALERTS = 'delivery.alerts';

    public const RUNS = 'delivery.runs';

    public const BOXES = 'delivery.boxes';

    public const CASH = 'delivery.cash';

    public const ACCOUNT = 'delivery.account';

    public const PROFILE = 'delivery.profile';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DASHBOARD,
            self::ALERTS,
            self::RUNS,
            self::BOXES,
            self::CASH,
            self::ACCOUNT,
            self::PROFILE,
        ];
    }

    public static function syncDeliveryRole(?Role $delivery = null): void
    {
        $delivery ??= Role::query()->where('name', 'delivery')->first();
        if (! $delivery) {
            return;
        }

        $ids = [];
        foreach (self::all() as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name]);
            $ids[] = $permission->id;
        }

        $delivery->permissions()->syncWithoutDetaching($ids);
    }
}
