<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;

class KitchenPermissions
{
    public const DASHBOARD = 'kitchen.dashboard';

    public const ALERTS = 'kitchen.alerts';

    public const ORDER_GROUPS = 'kitchen.order-groups';

    public const ORDERS = 'kitchen.orders';

    public const MENUS = 'kitchen.menus';

    public const PREP = 'kitchen.prep';

    public const BOXES = 'kitchen.boxes';

    public const ACCOUNT = 'kitchen.account';

    public const CASH_HANDOVERS = 'kitchen.cash-handovers';

    public const COMPLAINTS = 'kitchen.complaints';

    public const PROFILE = 'kitchen.profile';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DASHBOARD,
            self::ALERTS,
            self::ORDER_GROUPS,
            self::ORDERS,
            self::MENUS,
            self::PREP,
            self::BOXES,
            self::ACCOUNT,
            self::CASH_HANDOVERS,
            self::COMPLAINTS,
            self::PROFILE,
        ];
    }

    /**
     * Legacy toy permissions removed from the kitchen matrix.
     *
     * @return list<string>
     */
    public static function legacyToDetach(): array
    {
        return ['edit-menu', 'accept-order', 'view-analytics'];
    }

    public static function syncKitchenRole(?Role $kitchen = null): void
    {
        $kitchen ??= Role::query()->where('name', 'kitchen')->first();
        if (! $kitchen) {
            return;
        }

        $ids = [];
        foreach (self::all() as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name]);
            $ids[] = $permission->id;
        }

        $kitchen->permissions()->syncWithoutDetaching($ids);

        $legacyIds = Permission::query()
            ->whereIn('name', self::legacyToDetach())
            ->pluck('id')
            ->all();

        if ($legacyIds !== []) {
            $kitchen->permissions()->detach($legacyIds);
        }
    }
}
