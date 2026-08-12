<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $deliveryId = Role::query()->where('name', 'delivery')->value('id');
        if (! $deliveryId) {
            return;
        }

        $exists = Nav::query()
            ->where('role_id', $deliveryId)
            ->where('route_name', 'delivery.alerts')
            ->exists();

        if ($exists) {
            return;
        }

        Nav::query()
            ->where('role_id', $deliveryId)
            ->whereNull('parent_id')
            ->where('order', '>=', 2)
            ->increment('order');

        Nav::create([
            'title' => 'Alerts',
            'route_name' => 'delivery.alerts',
            'order' => 2,
            'role_id' => $deliveryId,
        ]);
    }

    public function down(): void
    {
        $deliveryId = Role::query()->where('name', 'delivery')->value('id');
        if (! $deliveryId) {
            return;
        }

        Nav::query()
            ->where('role_id', $deliveryId)
            ->where('route_name', 'delivery.alerts')
            ->delete();
    }
};
