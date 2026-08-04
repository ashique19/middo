<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $accountsId = Role::query()->where('name', 'accounts')->value('id');
        if (! $accountsId) {
            return;
        }

        $items = [
            ['title' => 'Rider cash handovers', 'route_name' => 'accounts.cash-handovers', 'icon' => '🤝', 'order' => 4],
            ['title' => 'Corporates', 'route_name' => 'accounts.corporates.index', 'icon' => '🏢', 'order' => 9],
        ];

        foreach ($items as $item) {
            Nav::query()->updateOrCreate(
                [
                    'role_id' => $accountsId,
                    'route_name' => $item['route_name'],
                ],
                [
                    'title' => $item['title'],
                    'icon' => $item['icon'],
                    'order' => $item['order'],
                    'parent_id' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        $accountsId = Role::query()->where('name', 'accounts')->value('id');
        if (! $accountsId) {
            return;
        }

        Nav::query()
            ->where('role_id', $accountsId)
            ->whereIn('route_name', ['accounts.cash-handovers', 'accounts.corporates.index'])
            ->delete();
    }
};
