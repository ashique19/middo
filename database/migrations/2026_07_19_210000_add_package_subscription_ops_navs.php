<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureChildNav('admin', 'Menu', [
            ['title' => 'Subscriptions', 'route_name' => 'admin.subscriptions.index', 'order' => 4],
            ['title' => 'Package demand', 'route_name' => 'admin.packages.demand', 'order' => 5],
            ['title' => 'Package insights', 'route_name' => 'admin.packages.insights', 'order' => 6],
        ]);

        $this->ensureChildNav('operation', 'Menu', [
            ['title' => 'Subscriptions', 'route_name' => 'operation.subscriptions.index', 'order' => 4],
            ['title' => 'Package demand', 'route_name' => 'operation.packages.demand', 'order' => 5],
            ['title' => 'Package insights', 'route_name' => 'operation.packages.insights', 'order' => 6],
        ]);

        $kitchenId = Role::query()->where('name', 'kitchen')->value('id');
        if ($kitchenId && ! Nav::query()->where('route_name', 'kitchen.menus.today')->exists()) {
            Nav::create([
                'title' => "Today's menus",
                'route_name' => 'kitchen.menus.today',
                'order' => 4,
                'role_id' => $kitchenId,
            ]);
        }
    }

    /**
     * @param  array<int, array{title: string, route_name: string, order: int}>  $children
     */
    protected function ensureChildNav(string $roleName, string $parentTitle, array $children): void
    {
        $roleId = Role::query()->where('name', $roleName)->value('id');
        if (! $roleId) {
            return;
        }

        $parentId = Nav::query()
            ->where('role_id', $roleId)
            ->where('title', $parentTitle)
            ->whereNull('parent_id')
            ->value('id');

        if (! $parentId) {
            return;
        }

        foreach ($children as $child) {
            if (Nav::query()->where('route_name', $child['route_name'])->exists()) {
                continue;
            }

            Nav::create([
                'title' => $child['title'],
                'route_name' => $child['route_name'],
                'order' => $child['order'],
                'role_id' => $roleId,
                'parent_id' => $parentId,
            ]);
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'admin.subscriptions.index',
            'admin.packages.demand',
            'admin.packages.insights',
            'operation.subscriptions.index',
            'operation.packages.demand',
            'operation.packages.insights',
            'kitchen.menus.today',
        ])->delete();
    }
};
