<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $kitchenId = Role::where('name', 'kitchen')->value('id');

        if (! $kitchenId) {
            return;
        }

        $items = [
            ['title' => 'Middo order groups', 'route_name' => 'kitchen.order-groups.middo', 'order' => 2],
            ['title' => 'My active orders', 'route_name' => 'kitchen.orders.active', 'order' => 3],
            ['title' => 'My orders this month', 'route_name' => 'kitchen.orders.this-month', 'order' => 4],
            ['title' => 'Last 3 months', 'route_name' => 'kitchen.orders.last-three-months', 'order' => 5],
        ];

        foreach ($items as $item) {
            $exists = Nav::query()
                ->where('role_id', $kitchenId)
                ->where('route_name', $item['route_name'])
                ->exists();

            if ($exists) {
                continue;
            }

            Nav::create([
                'title' => $item['title'],
                'route_name' => $item['route_name'],
                'order' => $item['order'],
                'role_id' => $kitchenId,
            ]);
        }
    }

    public function down(): void
    {
        $kitchenId = Role::where('name', 'kitchen')->value('id');

        if (! $kitchenId) {
            return;
        }

        Nav::query()
            ->where('role_id', $kitchenId)
            ->whereIn('route_name', [
                'kitchen.order-groups.middo',
                'kitchen.orders.active',
                'kitchen.orders.this-month',
                'kitchen.orders.last-three-months',
            ])
            ->delete();
    }
};
