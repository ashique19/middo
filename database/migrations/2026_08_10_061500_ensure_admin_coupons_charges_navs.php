<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Coupons/Charges nav migrations (2026_07_22_064100 / 070100) no-op when the
 * admin role is missing (typical on migrate:fresh before seeders). NavSeeder
 * also omitted them. Re-assert both under the admin Menu group.
 */
return new class extends Migration
{
    public function up(): void
    {
        $adminId = Role::query()->where('name', 'admin')->value('id');
        if (! $adminId) {
            return;
        }

        $adminMenuParent = Nav::query()
            ->where('role_id', $adminId)
            ->whereIn('title', ['Catalog', 'Menu'])
            ->whereNull('parent_id')
            ->whereNull('route_name')
            ->orderByRaw("CASE WHEN title = 'Catalog' THEN 0 ELSE 1 END")
            ->value('id');

        foreach ([
            ['title' => 'Coupons', 'route_name' => 'admin.coupons.index', 'order' => 7],
            ['title' => 'Charges', 'route_name' => 'admin.charges.index', 'order' => 8],
        ] as $item) {
            Nav::query()->updateOrCreate(
                [
                    'role_id' => $adminId,
                    'route_name' => $item['route_name'],
                ],
                [
                    'title' => $item['title'],
                    'order' => $item['order'],
                    'parent_id' => $adminMenuParent,
                ]
            );
        }
    }

    public function down(): void
    {
        Nav::query()
            ->whereIn('route_name', ['admin.coupons.index', 'admin.charges.index'])
            ->delete();
    }
};
