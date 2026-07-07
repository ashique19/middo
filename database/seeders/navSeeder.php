<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Nav;

class navSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // database/seeders/NavSeeder.php
        Nav::create(['title' => 'Dashboard', 'route_name' => 'admin.dashboard', 'order' => 1, 'role_id' => 1]);
        Nav::create(['title' => 'Kitchens', 'route_name' => 'admin.kitchen.index', 'order' => 2, 'role_id' => 1]);
        
        Nav::create(['title' => 'Dashboard', 'route_name' => 'operation.dashboard', 'order' => 1, 'role_id' => 5]);
        Nav::create(['title' => 'Kitchens', 'route_name' => 'operation.kitchens.index', 'order' => 2, 'role_id' => 5]);
        Nav::create(['title' => 'Menu', 'route_name' => 'operation.menu.index', 'order' => 3, 'role_id' => 5]);

        $ordersNav = Nav::create([
            'title' => 'Orders',
            'route_name' => null,
            'icon' => '📦',
            'order' => 4,
            'role_id' => 5,
        ]);

        Nav::create(['title' => 'Active orders', 'route_name' => 'operation.orders.active', 'order' => 1, 'role_id' => 5, 'parent_id' => $ordersNav->id]);
        Nav::create(['title' => 'Order History', 'route_name' => 'operation.orders.history', 'order' => 2, 'role_id' => 5, 'parent_id' => $ordersNav->id]);
        Nav::create(['title' => 'Search Order', 'route_name' => 'operation.orders.search', 'order' => 3, 'role_id' => 5, 'parent_id' => $ordersNav->id]);

        Nav::create([
            'title' => 'Middo Boxes',
            'route_name' => 'operation.middo-boxes.index',
            'icon' => '📦',
            'order' => 5,
            'role_id' => 5,
        ]);

    }
}
