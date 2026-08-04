<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        if (! $kitchenRoleId) {
            return;
        }

        $exists = Nav::query()
            ->where('role_id', $kitchenRoleId)
            ->where('route_name', 'kitchen.prep.shopping-list')
            ->exists();

        if ($exists) {
            return;
        }

        $todayOrder = (int) Nav::query()
            ->where('role_id', $kitchenRoleId)
            ->where('route_name', 'kitchen.menus.today')
            ->value('order');

        $insertAt = $todayOrder > 0 ? $todayOrder + 1 : 5;

        Nav::query()
            ->where('role_id', $kitchenRoleId)
            ->whereNull('parent_id')
            ->where('order', '>=', $insertAt)
            ->increment('order');

        Nav::create([
            'title' => 'Prep shopping list',
            'route_name' => 'kitchen.prep.shopping-list',
            'order' => $insertAt,
            'role_id' => $kitchenRoleId,
        ]);
    }

    public function down(): void
    {
        Nav::query()->where('route_name', 'kitchen.prep.shopping-list')->delete();
    }
};
