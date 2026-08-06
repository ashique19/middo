<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promote operation package navs out from under Menu into a top-level
     * Packages group placed immediately after Orders.
     */
    public function up(): void
    {
        if (! Schema::hasTable('navs') || ! Schema::hasTable('roles')) {
            return;
        }

        $roleId = Role::query()->where('name', 'operation')->value('id');
        if (! $roleId) {
            return;
        }

        $ordersOrder = (int) (Nav::query()
            ->where('role_id', $roleId)
            ->where('title', 'Orders')
            ->whereNull('parent_id')
            ->value('order') ?? 5);

        $packagesParent = Nav::query()
            ->where('role_id', $roleId)
            ->where('title', 'Packages')
            ->whereNull('parent_id')
            ->whereNull('route_name')
            ->first();

        if (! $packagesParent) {
            // Make room after Orders for the new Packages group.
            Nav::query()
                ->where('role_id', $roleId)
                ->whereNull('parent_id')
                ->where('order', '>', $ordersOrder)
                ->increment('order');

            $packagesParent = Nav::create([
                'title' => 'Packages',
                'route_name' => null,
                'icon' => '🍱',
                'order' => $ordersOrder + 1,
                'role_id' => $roleId,
            ]);
        } else {
            $packagesParent->update([
                'icon' => $packagesParent->icon ?: '🍱',
                'order' => $ordersOrder + 1,
            ]);
        }

        $children = [
            ['title' => 'Packages', 'route_name' => 'operation.packages.index', 'order' => 1],
            ['title' => 'Subscriptions', 'route_name' => 'operation.subscriptions.index', 'order' => 2],
            ['title' => 'Package demand', 'route_name' => 'operation.packages.demand', 'order' => 3],
            ['title' => 'Package insights', 'route_name' => 'operation.packages.insights', 'order' => 4],
        ];

        foreach ($children as $child) {
            $nav = Nav::query()
                ->where('role_id', $roleId)
                ->where('route_name', $child['route_name'])
                ->first();

            if ($nav) {
                $nav->update([
                    'title' => $child['title'],
                    'parent_id' => $packagesParent->id,
                    'order' => $child['order'],
                ]);

                continue;
            }

            Nav::create([
                'title' => $child['title'],
                'route_name' => $child['route_name'],
                'order' => $child['order'],
                'role_id' => $roleId,
                'parent_id' => $packagesParent->id,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('navs') || ! Schema::hasTable('roles')) {
            return;
        }

        $roleId = Role::query()->where('name', 'operation')->value('id');
        if (! $roleId) {
            return;
        }

        $menuParentId = Nav::query()
            ->where('role_id', $roleId)
            ->where('title', 'Menu')
            ->whereNull('parent_id')
            ->value('id');

        $packagesParent = Nav::query()
            ->where('role_id', $roleId)
            ->where('title', 'Packages')
            ->whereNull('parent_id')
            ->whereNull('route_name')
            ->first();

        if (! $packagesParent) {
            return;
        }

        $restore = [
            'operation.packages.index' => 3,
            'operation.subscriptions.index' => 4,
            'operation.packages.demand' => 5,
            'operation.packages.insights' => 6,
        ];

        foreach ($restore as $route => $order) {
            Nav::query()
                ->where('role_id', $roleId)
                ->where('route_name', $route)
                ->update([
                    'parent_id' => $menuParentId,
                    'order' => $order,
                ]);
        }

        $packagesParent->delete();
    }
};
