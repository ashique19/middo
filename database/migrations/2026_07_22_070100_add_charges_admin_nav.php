<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $adminId = Role::query()->where('name', 'admin')->value('id');
        if (! $adminId) {
            return;
        }

        if (Nav::query()->where('route_name', 'admin.charges.index')->exists()) {
            return;
        }

        $adminMenuParent = Nav::query()
            ->where('role_id', $adminId)
            ->where('title', 'Menu')
            ->whereNull('parent_id')
            ->value('id');

        Nav::create([
            'title' => 'Charges',
            'route_name' => 'admin.charges.index',
            'order' => 5,
            'role_id' => $adminId,
            'parent_id' => $adminMenuParent,
        ]);
    }

    public function down(): void
    {
        Nav::query()->where('route_name', 'admin.charges.index')->delete();
    }
};
