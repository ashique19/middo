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

        $items = [
            ['admin', 'COD recon', 'admin.cod-recon.index', '📊'],
            ['operation', 'COD recon', 'operation.cod-recon.index', '📊'],
            ['admin', 'Operating costs', 'admin.operating-costs.index', '📉'],
            ['operation', 'Operating costs', 'operation.operating-costs.index', '📉'],
        ];

        foreach ($items as [$roleName, $title, $route, $icon]) {
            $roleId = $Role::query()->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            $exists = $Nav::query()
                ->where('role_id', $roleId)
                ->where('route_name', $route)
                ->exists();
            if ($exists) {
                continue;
            }
            $max = (int) $Nav::query()->where('role_id', $roleId)->whereNull('parent_id')->max('order');
            $Nav::create([
                'title' => $title,
                'route_name' => $route,
                'icon' => $icon,
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
            'admin.cod-recon.index',
            'operation.cod-recon.index',
            'admin.operating-costs.index',
            'operation.operating-costs.index',
        ])->delete();
    }
};
