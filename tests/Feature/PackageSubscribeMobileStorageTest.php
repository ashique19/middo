<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateOrderPrepayment;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageSubscribeMobileStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_local_mobile_strips_sms_country_prefix(): void
    {
        $this->assertSame('01410123456', CorporateOrderPrepayment::toLocalMobile('01410123456'));
        $this->assertSame('01410123456', CorporateOrderPrepayment::toLocalMobile('8801410123456'));
        $this->assertSame('01410123456', CorporateOrderPrepayment::toLocalMobile('+880 1410-123456'));
        $this->assertSame('', CorporateOrderPrepayment::toLocalMobile('not-a-mobile'));
    }

    public function test_package_subscribe_does_not_rewrite_profile_mobile_to_880_format(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        $role = Role::create(['name' => 'corporate']);
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        // Simulate a legacy/buggy row that already holds the SMS-normalized form.
        User::create([
            'first_name' => 'Other',
            'last_name' => 'Corp',
            'company_name' => 'Other Co',
            'mobile' => '8801410123456',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 1000,
            'address' => 'Elsewhere',
        ]);

        $buyer = User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01410123456',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 50000,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'address' => 'House 12, Road 5',
        ]);

        $menu = MenuItem::create([
            'name' => 'Office Thali',
            'summary' => 'Daily lunch',
            'price' => 150,
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $start = now(OrderCutoff::timezone())->startOfMonth();
        $package = MealPackage::create([
            'name' => '৳79/day Classic',
            'summary' => 'Monthly rate plan',
            'price_per_day' => 79,
            'diet_tag' => 'classic',
            'duration_days' => 30,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);

        $month = '2026-08';
        $omitted = [5, 6];
        $dayCount = PackageBilling::availableDatesInMonth($month, $omitted)->count();

        $result = app(PackageSubscriptionService::class)->subscribe(
            $buyer,
            $package,
            1,
            $omitted,
            [['menu_item_id' => $menu->id, 'day_count' => $dayCount]],
            $month,
            'Corporate User',
            '01410123456',
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );

        $this->assertNotNull($result['subscription']);
        $this->assertSame('01410123456', $buyer->fresh()->mobile);
    }

    public function test_package_subscribe_accepts_880_receiver_mobile_without_colliding(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        $role = Role::create(['name' => 'corporate']);
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        User::create([
            'first_name' => 'Other',
            'last_name' => 'Corp',
            'company_name' => 'Other Co',
            'mobile' => '8801410123456',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 1000,
            'address' => 'Elsewhere',
        ]);

        $buyer = User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01410123456',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 50000,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'address' => 'House 12, Road 5',
        ]);

        $menu = MenuItem::create([
            'name' => 'Office Thali',
            'summary' => 'Daily lunch',
            'price' => 150,
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $start = now(OrderCutoff::timezone())->startOfMonth();
        $package = MealPackage::create([
            'name' => '৳79/day Classic',
            'summary' => 'Monthly rate plan',
            'price_per_day' => 79,
            'diet_tag' => 'classic',
            'duration_days' => 30,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);

        $month = '2026-08';
        $omitted = [5, 6];
        $dayCount = PackageBilling::availableDatesInMonth($month, $omitted)->count();

        // Receiver entered with country code; profile still matches via normalizeMobile.
        $result = app(PackageSubscriptionService::class)->subscribe(
            $buyer,
            $package,
            1,
            $omitted,
            [['menu_item_id' => $menu->id, 'day_count' => $dayCount]],
            $month,
            'Corporate User',
            '8801410123456',
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );

        $this->assertNotNull($result['subscription']);
        $this->assertSame('01410123456', $buyer->fresh()->mobile);
    }
}
