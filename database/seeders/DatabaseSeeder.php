<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            AreaSeeder::class,
            RolePermissionSeeder::class,
            NavSeeder::class,
            SitePageSeeder::class,
            UserSeeder::class,
            MenuItemSeeder::class,
            MealItemTestSeeder::class,
            MiddoBoxTestSeeder::class,
            OrderSeeder::class,
            TestOrderSeeder::class,
            KitchenDeliveryDashboardSeeder::class,
            OrderLogSeeder::class,
            OrderComplaintSeeder::class,
        ]);
    }
}
