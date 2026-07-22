<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['admin', 'operation'] as $roleName) {
            $roleId = Role::query()->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            $route = $roleName.'.accounts.index';
            if (Nav::query()->where('route_name', $route)->exists()) {
                continue;
            }

            Nav::create([
                'title' => 'Accounts',
                'route_name' => $route,
                'order' => 48,
                'role_id' => $roleId,
                'parent_id' => null,
            ]);
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'admin.accounts.index',
            'operation.accounts.index',
        ])->delete();
    }
};
