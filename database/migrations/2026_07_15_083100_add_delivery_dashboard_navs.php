<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $deliveryId = Role::where('name', 'delivery')->value('id');

        if (! $deliveryId) {
            return;
        }

        $items = [
            ['title' => 'Kitchen dispatches', 'route_name' => 'delivery.kitchen-dispatches', 'order' => 2],
            ['title' => 'Middo boxes pending run', 'route_name' => 'delivery.middo-boxes.pending-run', 'order' => 3],
        ];

        foreach ($items as $item) {
            $exists = Nav::query()
                ->where('role_id', $deliveryId)
                ->where('route_name', $item['route_name'])
                ->exists();

            if ($exists) {
                continue;
            }

            Nav::create([
                'title' => $item['title'],
                'route_name' => $item['route_name'],
                'order' => $item['order'],
                'role_id' => $deliveryId,
            ]);
        }
    }

    public function down(): void
    {
        $deliveryId = Role::where('name', 'delivery')->value('id');

        if (! $deliveryId) {
            return;
        }

        Nav::query()
            ->where('role_id', $deliveryId)
            ->whereIn('route_name', [
                'delivery.kitchen-dispatches',
                'delivery.middo-boxes.pending-run',
            ])
            ->delete();
    }
};
