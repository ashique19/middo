<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Permissions
        $p1 = Permission::create(['name' => 'edit-menu']);
        $p2 = Permission::create(['name' => 'accept-order']);
        $p3 = Permission::create(['name' => 'view-analytics']);

        // 2. Create Roles
        $admin = Role::create(['id' => 1, 'name' => 'admin']);
        $corporate = Role::create(['id' => 2, 'name' => 'corporate']);
        $kitchen = Role::create(['id' => 3, 'name' => 'kitchen']);
        $delivery = Role::create(['id' => 4, 'name' => 'delivery']);
        $operations = Role::create(['id' => 5, 'name' => 'operation']);

        // 3. Assign Permissions
        // Example: assign permissions to roles
        $kitchen->permissions()->attach([$p1->id, $p2->id]);
        $delivery->permissions()->attach([$p2->id]); // Delivery users might only accept orders
        $operations->permissions()->attach([$p2->id]); // Operations users might only accept orders
        $admin->permissions()->attach([$p1->id, $p2->id, $p3->id]);
        
        // Corporate users might not have these specific permissions 
        // based on your current setup, but the role exists now.
    }
}