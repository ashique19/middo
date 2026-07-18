<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $adminId = Role::query()->where('name', 'admin')->value('id');
        $operationId = Role::query()->where('name', 'operation')->value('id');

        if ($adminId) {
            $adminMenuParent = Nav::query()
                ->where('role_id', $adminId)
                ->where('title', 'Menu')
                ->whereNull('parent_id')
                ->value('id');

            if ($adminMenuParent && ! Nav::query()->where('route_name', 'admin.packages.index')->exists()) {
                Nav::create([
                    'title' => 'Packages',
                    'route_name' => 'admin.packages.index',
                    'order' => 3,
                    'role_id' => $adminId,
                    'parent_id' => $adminMenuParent,
                ]);
            }
        }

        if ($operationId) {
            $opsMenuParent = Nav::query()
                ->where('role_id', $operationId)
                ->where('title', 'Menu')
                ->whereNull('parent_id')
                ->value('id');

            if ($opsMenuParent && ! Nav::query()->where('route_name', 'operation.packages.index')->exists()) {
                Nav::create([
                    'title' => 'Packages',
                    'route_name' => 'operation.packages.index',
                    'order' => 3,
                    'role_id' => $operationId,
                    'parent_id' => $opsMenuParent,
                ]);
            }
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'admin.packages.index',
            'operation.packages.index',
        ])->delete();
    }
};
