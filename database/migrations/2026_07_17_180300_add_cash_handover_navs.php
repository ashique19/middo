<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $deliveryRole = Role::query()->where('name', 'delivery')->first();
        $kitchenRole = Role::query()->where('name', 'kitchen')->first();
        $adminRole = Role::query()->where('name', 'admin')->first();
        $operationRole = Role::query()->where('name', 'operation')->first();

        if ($deliveryRole) {
            $this->upsertNav($deliveryRole->id, 'Cash handovers', 'delivery.cash-handovers', 4);
        }

        if ($kitchenRole) {
            $this->upsertNav($kitchenRole->id, 'Cash handovers', 'kitchen.cash-handovers', 3);
        }

        foreach ([$adminRole, $operationRole] as $role) {
            if (! $role) {
                continue;
            }

            $route = $role->name === 'operation' ? 'operation.middo-cash' : 'admin.middo-cash';
            $this->upsertNav($role->id, 'Middo cash', $route, 50);
        }
    }

    public function down(): void
    {
        Nav::query()
            ->whereIn('route_name', [
                'delivery.cash-handovers',
                'kitchen.cash-handovers',
                'admin.middo-cash',
                'operation.middo-cash',
            ])
            ->delete();
    }

    protected function upsertNav(int $roleId, string $title, string $route, int $order): void
    {
        $existing = Nav::query()
            ->where('role_id', $roleId)
            ->where('route_name', $route)
            ->first();

        if ($existing) {
            return;
        }

        Nav::create([
            'role_id' => $roleId,
            'title' => $title,
            'route_name' => $route,
            'icon' => null,
            'order' => $order,
            'parent_id' => null,
        ]);
    }
};
