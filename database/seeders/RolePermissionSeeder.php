<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\KitchenPermissions;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $editMenu = Permission::query()->firstOrCreate(['name' => 'edit-menu']);
        $acceptOrder = Permission::query()->firstOrCreate(['name' => 'accept-order']);
        $viewAnalytics = Permission::query()->firstOrCreate(['name' => 'view-analytics']);

        $admin = Role::query()->firstOrCreate(['name' => 'admin']);
        Role::query()->firstOrCreate(['name' => 'corporate']);
        $kitchen = Role::query()->firstOrCreate(['name' => 'kitchen']);
        $delivery = Role::query()->firstOrCreate(['name' => 'delivery']);
        $operations = Role::query()->firstOrCreate(['name' => 'operation']);
        $accounts = Role::query()->firstOrCreate(['name' => 'accounts']);
        Role::query()->firstOrCreate(['name' => 'ground_marketing']);

        KitchenPermissions::syncKitchenRole($kitchen);

        $delivery->permissions()->syncWithoutDetaching([$acceptOrder->id]);
        $operations->permissions()->syncWithoutDetaching([$acceptOrder->id]);
        $accounts->permissions()->syncWithoutDetaching([$viewAnalytics->id]);
        $admin->permissions()->syncWithoutDetaching([
            $editMenu->id,
            $acceptOrder->id,
            $viewAnalytics->id,
        ]);
    }
}
