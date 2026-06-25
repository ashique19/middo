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

    }
}
