<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Nav;
use App\Models\Role;

class navSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = Role::where('name', 'admin')->value('id') ?? 1;
        $corporateId = Role::where('name', 'corporate')->value('id') ?? 2;
        $kitchenId = Role::where('name', 'kitchen')->value('id') ?? 3;
        $deliveryId = Role::where('name', 'delivery')->value('id') ?? 4;
        $operationId = Role::where('name', 'operation')->value('id') ?? 5;

        // ── Admin ────────────────────────────────────────────────────────────
        Nav::create(['title' => 'Dashboard', 'route_name' => 'admin.dashboard', 'order' => 1, 'role_id' => $adminId]);

        $adminKitchensNav = Nav::create([
            'title' => 'Kitchens',
            'route_name' => null,
            'icon' => '👨‍🍳',
            'order' => 2,
            'role_id' => $adminId,
        ]);
        Nav::create(['title' => 'Active Kitchens', 'route_name' => 'admin.kitchens.active', 'order' => 1, 'role_id' => $adminId, 'parent_id' => $adminKitchensNav->id]);
        Nav::create(['title' => 'Onboarding', 'route_name' => 'admin.kitchens.onboarding', 'order' => 2, 'role_id' => $adminId, 'parent_id' => $adminKitchensNav->id]);

        $adminMenuNav = Nav::create([
            'title' => 'Menu',
            'route_name' => null,
            'icon' => '🍽️',
            'order' => 3,
            'role_id' => $adminId,
        ]);
        Nav::create(['title' => 'Menu items', 'route_name' => 'admin.menu.index', 'order' => 1, 'role_id' => $adminId, 'parent_id' => $adminMenuNav->id]);
        Nav::create(['title' => 'Meal items', 'route_name' => 'admin.meal-items.index', 'order' => 2, 'role_id' => $adminId, 'parent_id' => $adminMenuNav->id]);

        $adminOrdersNav = Nav::create([
            'title' => 'Orders',
            'route_name' => null,
            'icon' => '📦',
            'order' => 4,
            'role_id' => $adminId,
        ]);
        Nav::create(['title' => 'Active orders', 'route_name' => 'admin.orders.active', 'order' => 1, 'role_id' => $adminId, 'parent_id' => $adminOrdersNav->id]);
        Nav::create(['title' => 'Order History', 'route_name' => 'admin.orders.history', 'order' => 2, 'role_id' => $adminId, 'parent_id' => $adminOrdersNav->id]);
        Nav::create(['title' => 'Search Order', 'route_name' => 'admin.orders.search', 'order' => 3, 'role_id' => $adminId, 'parent_id' => $adminOrdersNav->id]);

        $adminUsersNav = Nav::create([
            'title' => 'Users',
            'route_name' => null,
            'icon' => '👥',
            'order' => 5,
            'role_id' => $adminId,
        ]);
        Nav::create(['title' => 'Admins', 'route_name' => 'admin.users.admin', 'order' => 1, 'role_id' => $adminId, 'parent_id' => $adminUsersNav->id]);
        Nav::create(['title' => 'Operations', 'route_name' => 'admin.users.operation', 'order' => 2, 'role_id' => $adminId, 'parent_id' => $adminUsersNav->id]);
        Nav::create(['title' => 'Kitchens', 'route_name' => 'admin.users.kitchen', 'order' => 3, 'role_id' => $adminId, 'parent_id' => $adminUsersNav->id]);
        Nav::create(['title' => 'Corporates', 'route_name' => 'admin.users.corporate', 'order' => 4, 'role_id' => $adminId, 'parent_id' => $adminUsersNav->id]);
        Nav::create(['title' => 'Delivery', 'route_name' => 'admin.users.delivery', 'order' => 5, 'role_id' => $adminId, 'parent_id' => $adminUsersNav->id]);

        Nav::create([
            'title' => 'System Navs',
            'route_name' => 'admin.navrole.index',
            'icon' => '⚙️',
            'order' => 6,
            'role_id' => $adminId,
        ]);

        // ── Operation ────────────────────────────────────────────────────────
        Nav::create(['title' => 'Dashboard', 'route_name' => 'operation.dashboard', 'order' => 1, 'role_id' => $operationId]);
        Nav::create(['title' => 'Kitchens', 'route_name' => 'operation.kitchens.index', 'order' => 2, 'role_id' => $operationId, 'icon' => '👨‍🍳']);

        $menuNav = Nav::create([
            'title' => 'Menu',
            'route_name' => null,
            'icon' => '🍽️',
            'order' => 3,
            'role_id' => $operationId,
        ]);
        Nav::create(['title' => 'Menu items', 'route_name' => 'operation.menu.index', 'order' => 1, 'role_id' => $operationId, 'parent_id' => $menuNav->id]);
        Nav::create(['title' => 'Meal items', 'route_name' => 'operation.meal-items.index', 'order' => 2, 'role_id' => $operationId, 'parent_id' => $menuNav->id]);

        $ordersNav = Nav::create([
            'title' => 'Orders',
            'route_name' => null,
            'icon' => '📦',
            'order' => 4,
            'role_id' => $operationId,
        ]);
        Nav::create(['title' => 'Active orders', 'route_name' => 'operation.orders.active', 'order' => 1, 'role_id' => $operationId, 'parent_id' => $ordersNav->id]);
        Nav::create(['title' => 'Order History', 'route_name' => 'operation.orders.history', 'order' => 2, 'role_id' => $operationId, 'parent_id' => $ordersNav->id]);
        Nav::create(['title' => 'Search Order', 'route_name' => 'operation.orders.search', 'order' => 3, 'role_id' => $operationId, 'parent_id' => $ordersNav->id]);

        Nav::create([
            'title' => 'Middo Boxes',
            'route_name' => 'operation.middo-boxes.index',
            'icon' => '📦',
            'order' => 5,
            'role_id' => $operationId,
        ]);

        // ── Other roles (private sidebar when used) ───────────────────────────
        Nav::create(['title' => 'Dashboard', 'route_name' => 'corporates.dashboard', 'order' => 1, 'role_id' => $corporateId]);
        Nav::create(['title' => 'Scheduled Orders', 'route_name' => 'corporates.orders.scheduled', 'order' => 2, 'role_id' => $corporateId]);
        Nav::create(['title' => 'Order History', 'route_name' => 'corporates.orders.history', 'order' => 3, 'role_id' => $corporateId]);

        Nav::create(['title' => 'Dashboard', 'route_name' => 'kitchen.dashboard', 'order' => 1, 'role_id' => $kitchenId]);
        Nav::create(['title' => 'Middo order groups', 'route_name' => 'kitchen.order-groups.middo', 'order' => 2, 'role_id' => $kitchenId]);
        Nav::create(['title' => 'My active orders', 'route_name' => 'kitchen.orders.active', 'order' => 3, 'role_id' => $kitchenId]);
        Nav::create(['title' => 'My orders this month', 'route_name' => 'kitchen.orders.this-month', 'order' => 4, 'role_id' => $kitchenId]);
        Nav::create(['title' => 'Last 3 months', 'route_name' => 'kitchen.orders.last-three-months', 'order' => 5, 'role_id' => $kitchenId]);

        $kitchenBoxesNav = Nav::create([
            'title' => 'Middo boxes',
            'route_name' => null,
            'icon' => '📦',
            'order' => 6,
            'role_id' => $kitchenId,
        ]);
        Nav::create(['title' => 'Boxes at kitchen', 'route_name' => 'kitchen.middo-boxes.at-kitchen', 'order' => 1, 'role_id' => $kitchenId, 'parent_id' => $kitchenBoxesNav->id]);
        Nav::create(['title' => 'Incoming', 'route_name' => 'kitchen.middo-boxes.incoming', 'order' => 2, 'role_id' => $kitchenId, 'parent_id' => $kitchenBoxesNav->id]);

        Nav::create(['title' => 'Dashboard', 'route_name' => 'delivery.dashboard', 'order' => 1, 'role_id' => $deliveryId]);
        Nav::create(['title' => 'Kitchen dispatches', 'route_name' => 'delivery.kitchen-dispatches', 'order' => 2, 'role_id' => $deliveryId]);
        Nav::create(['title' => 'Middo boxes pending run', 'route_name' => 'delivery.middo-boxes.pending-run', 'order' => 3, 'role_id' => $deliveryId]);
        Nav::create(['title' => 'Delivered orders', 'route_name' => 'delivery.orders.delivered', 'order' => 4, 'role_id' => $deliveryId]);
    }
}
