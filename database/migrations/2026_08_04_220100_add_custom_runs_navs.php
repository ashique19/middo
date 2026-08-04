<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('navs') || ! Schema::hasTable('roles')) {
            return;
        }

        $Nav = \App\Models\Nav::class;
        $Role = \App\Models\Role::class;

        foreach (['admin', 'operation'] as $roleName) {
            $roleId = $Role::query()->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            $route = $roleName === 'admin'
                ? 'admin.custom-runs.index'
                : 'operation.custom-runs.index';
            if ($Nav::query()->where('role_id', $roleId)->where('route_name', $route)->exists()) {
                continue;
            }
            $max = (int) $Nav::query()->where('role_id', $roleId)->whereNull('parent_id')->max('order');
            $Nav::create([
                'title' => 'Custom runs',
                'route_name' => $route,
                'icon' => '📍',
                'order' => $max + 1,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('navs')) {
            return;
        }

        DB::table('navs')->whereIn('route_name', [
            'admin.custom-runs.index',
            'operation.custom-runs.index',
        ])->delete();
    }
};
