<?php

namespace Tests\Feature\Delivery;

use App\Models\CashHandover;
use App\Models\RiderWithdrawalRequest;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\RiderAccountLedger;
use Database\Seeders\AreaSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\DeliveryFeaturesTestSeeder;
use Database\Seeders\MenuItemSeeder;
use Database\Seeders\NavSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFeaturesTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_features_seeder_builds_demo_fixtures(): void
    {
        $this->seed([
            CitySeeder::class,
            AreaSeeder::class,
            RolePermissionSeeder::class,
            NavSeeder::class,
            UserSeeder::class,
            MenuItemSeeder::class,
            DeliveryFeaturesTestSeeder::class,
        ]);

        $rider = User::query()->where('email', 'delivery@middo.com')->first();
        $this->assertNotNull($rider);
        $this->assertNotEmpty($rider->serviceAreaIds());
        $this->assertGreaterThan(0, RiderAccountLedger::balance($rider->id));
        $this->assertDatabaseHas('rider_withdrawal_requests', [
            'rider_user_id' => $rider->id,
            'status' => RiderWithdrawalRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('cash_handovers', [
            'rider_id' => $rider->id,
            'status' => 'pending',
            'target' => CashHandover::TARGET_KITCHEN,
        ]);
        $this->assertDatabaseHas('middo_operating_costs', [
            'rider_user_id' => $rider->id,
            'run_type' => 'ops_to_kitchen',
        ]);
        $this->assertTrue(
            StaffAlert::query()
                ->where('user_id', $rider->id)
                ->where('type', StaffAlert::TYPE_LUNCH_DISPATCH)
                ->exists()
        );
        $this->assertDatabaseHas('menu_items', [
            'name' => 'Vegetable Khichdi Thali',
        ]);
        $this->assertGreaterThan(
            0,
            (int) \App\Models\MenuItem::query()->where('name', 'Vegetable Khichdi Thali')->value('delivery_commission')
        );
    }
}
