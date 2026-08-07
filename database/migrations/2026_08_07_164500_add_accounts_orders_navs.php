<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $accountsId = Role::query()->where('name', 'accounts')->value('id');
        if (! $accountsId) {
            return;
        }

        $parent = Nav::query()->updateOrCreate(
            ['role_id' => $accountsId, 'title' => 'Orders', 'route_name' => null],
            ['icon' => '🧾', 'order' => 11, 'parent_id' => null]
        );

        Nav::query()->updateOrCreate(
            ['role_id' => $accountsId, 'route_name' => 'accounts.orders.active'],
            ['title' => 'Active orders', 'order' => 1, 'parent_id' => $parent->id]
        );

        Nav::query()->updateOrCreate(
            ['role_id' => $accountsId, 'route_name' => 'accounts.orders.history'],
            ['title' => 'Order History', 'order' => 2, 'parent_id' => $parent->id]
        );

        Nav::query()->updateOrCreate(
            ['role_id' => $accountsId, 'route_name' => 'accounts.orders.search'],
            ['title' => 'Search Order', 'order' => 3, 'parent_id' => $parent->id]
        );
    }

    public function down(): void
    {
        $accountsId = Role::query()->where('name', 'accounts')->value('id');
        if (! $accountsId) {
            return;
        }

        Nav::query()
            ->where('role_id', $accountsId)
            ->whereIn('route_name', [
                'accounts.orders.active',
                'accounts.orders.history',
                'accounts.orders.search',
            ])
            ->delete();

        Nav::query()
            ->where('role_id', $accountsId)
            ->whereNull('route_name')
            ->where('title', 'Orders')
            ->delete();
    }
};
