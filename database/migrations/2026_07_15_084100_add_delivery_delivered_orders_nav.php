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

        $exists = Nav::query()
            ->where('role_id', $deliveryId)
            ->where('route_name', 'delivery.orders.delivered')
            ->exists();

        if ($exists) {
            return;
        }

        Nav::create([
            'title' => 'Delivered orders',
            'route_name' => 'delivery.orders.delivered',
            'order' => 4,
            'role_id' => $deliveryId,
        ]);
    }

    public function down(): void
    {
        $deliveryId = Role::where('name', 'delivery')->value('id');

        if (! $deliveryId) {
            return;
        }

        Nav::query()
            ->where('role_id', $deliveryId)
            ->where('route_name', 'delivery.orders.delivered')
            ->delete();
    }
};
