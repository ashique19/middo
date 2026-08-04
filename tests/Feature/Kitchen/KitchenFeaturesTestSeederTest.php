<?php

namespace Tests\Feature\Kitchen;

use Database\Seeders\AreaSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\KitchenFeaturesTestSeeder;
use Database\Seeders\MenuItemSeeder;
use Database\Seeders\NavSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenFeaturesTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_features_seeder_builds_demo_fixtures(): void
    {
        $this->seed([
            CitySeeder::class,
            AreaSeeder::class,
            RolePermissionSeeder::class,
            NavSeeder::class,
            UserSeeder::class,
            MenuItemSeeder::class,
            KitchenFeaturesTestSeeder::class,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'kitchen@middo.com',
            'kitchen_tier' => 'gold',
            'allowed_open_groups' => 2,
        ]);

        $this->assertDatabaseHas('order_groups', ['name' => 'GRP-KFEAT-OPEN']);
        $this->assertDatabaseHas('order_groups', ['name' => 'GRP-KFEAT-ACTIVE']);
        $this->assertDatabaseHas('kitchen_hours', ['day_of_week' => 1]);
        $this->assertDatabaseHas('kitchen_withdrawal_requests', ['status' => 'pending']);
        $this->assertDatabaseHas('kitchen_middo_transfers', ['status' => 'pending']);
        $this->assertDatabaseHas('middo_boxes', ['asset_status' => 'damaged']);
        $this->assertDatabaseHas('staff_alerts', ['type' => 'accept_window_closing']);
        $this->assertDatabaseHas('order_complaints', ['category' => 'food_quality']);
    }
}
