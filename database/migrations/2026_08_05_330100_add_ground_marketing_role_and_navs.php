<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'ground_marketing'],
            ['id' => 7]
        );

        Nav::query()->updateOrCreate(
            ['role_id' => $role->id, 'route_name' => 'marketing.dashboard'],
            ['title' => 'Dashboard', 'order' => 1, 'icon' => '🏠']
        );
        Nav::query()->updateOrCreate(
            ['role_id' => $role->id, 'route_name' => 'marketing.companies.index'],
            ['title' => 'Companies', 'order' => 2, 'icon' => '🏢']
        );

        $adminId = Role::query()->where('name', 'admin')->value('id');
        if ($adminId) {
            $parent = Nav::query()
                ->where('role_id', $adminId)
                ->where('title', 'Users')
                ->whereNull('parent_id')
                ->value('id');

            Nav::query()->updateOrCreate(
                ['role_id' => $adminId, 'route_name' => 'admin.users.ground_marketing'],
                [
                    'title' => 'Ground marketing',
                    'order' => 7,
                    'parent_id' => $parent,
                    'icon' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'marketing.dashboard',
            'marketing.companies.index',
            'admin.users.ground_marketing',
        ])->delete();

        // Keep role row — removing may orphan users.
    }
};
