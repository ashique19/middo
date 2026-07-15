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

        $parent = Nav::query()
            ->where('role_id', $kitchenId)
            ->whereNull('route_name')
            ->where('title', 'Middo boxes')
            ->first();

        if (! $parent) {
            $parent = Nav::create([
                'title' => 'Middo boxes',
                'route_name' => null,
                'icon' => '📦',
                'order' => 6,
                'role_id' => $kitchenId,
            ]);
        }

        $children = [
            ['title' => 'Boxes at kitchen', 'route_name' => 'kitchen.middo-boxes.at-kitchen', 'order' => 1],
            ['title' => 'Incoming', 'route_name' => 'kitchen.middo-boxes.incoming', 'order' => 2],
        ];

        foreach ($children as $child) {
            $exists = Nav::query()
                ->where('role_id', $kitchenId)
                ->where('route_name', $child['route_name'])
                ->exists();

            if ($exists) {
                continue;
            }

            Nav::create([
                'title' => $child['title'],
                'route_name' => $child['route_name'],
                'order' => $child['order'],
                'role_id' => $kitchenId,
                'parent_id' => $parent->id,
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
                'kitchen.middo-boxes.at-kitchen',
                'kitchen.middo-boxes.incoming',
            ])
            ->delete();

        Nav::query()
            ->where('role_id', $kitchenId)
            ->whereNull('route_name')
            ->where('title', 'Middo boxes')
            ->delete();
    }
};
