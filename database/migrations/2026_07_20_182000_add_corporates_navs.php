<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->first();
        $operationRole = Role::query()->where('name', 'operation')->first();

        if ($adminRole) {
            $adminCorporateNav = Nav::query()
                ->where('role_id', $adminRole->id)
                ->whereIn('route_name', ['admin.users.corporate', 'admin.corporates.index'])
                ->first();

            if ($adminCorporateNav) {
                $adminCorporateNav->update([
                    'title' => 'Corporates',
                    'route_name' => 'admin.corporates.index',
                ]);
            } else {
                $usersParent = Nav::query()
                    ->where('role_id', $adminRole->id)
                    ->where('title', 'Users')
                    ->whereNull('parent_id')
                    ->first();

                Nav::create([
                    'role_id' => $adminRole->id,
                    'title' => 'Corporates',
                    'route_name' => 'admin.corporates.index',
                    'icon' => null,
                    'order' => 4,
                    'parent_id' => $usersParent?->id,
                ]);
            }
        }

        if ($operationRole) {
            $exists = Nav::query()
                ->where('role_id', $operationRole->id)
                ->where('route_name', 'operation.corporates.index')
                ->exists();

            if (! $exists) {
                // Sit between Dashboard (1) and Kitchens; bump kitchens+ if needed.
                $kitchens = Nav::query()
                    ->where('role_id', $operationRole->id)
                    ->where('route_name', 'operation.kitchens.index')
                    ->first();

                if ($kitchens && (int) $kitchens->order <= 2) {
                    Nav::query()
                        ->where('role_id', $operationRole->id)
                        ->whereNull('parent_id')
                        ->where('order', '>=', 2)
                        ->increment('order');
                }

                Nav::create([
                    'role_id' => $operationRole->id,
                    'title' => 'Corporates',
                    'route_name' => 'operation.corporates.index',
                    'icon' => '🏢',
                    'order' => 2,
                    'parent_id' => null,
                ]);
            }
        }
    }

    public function down(): void
    {
        Nav::query()
            ->where('route_name', 'operation.corporates.index')
            ->delete();

        Nav::query()
            ->where('route_name', 'admin.corporates.index')
            ->update(['route_name' => 'admin.users.corporate']);
    }
};
