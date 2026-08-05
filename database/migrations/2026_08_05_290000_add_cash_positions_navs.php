<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['admin', 'accounts', 'operation'] as $roleName) {
            $roleId = Role::query()->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            Nav::query()->updateOrCreate(
                ['role_id' => $roleId, 'route_name' => $roleName.'.cash-positions'],
                ['title' => 'Cash positions', 'order' => $roleName === 'accounts' ? 3 : 45, 'icon' => '💰']
            );
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'admin.cash-positions',
            'accounts.cash-positions',
            'operation.cash-positions',
        ])->delete();
    }
};
