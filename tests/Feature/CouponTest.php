<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Support\CouponService;
use App\Support\OrderCutoff;
use App\Support\PackageSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $corporateRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->corporateRole = Role::create(['name' => 'corporate']);
    }

    private function makeCorporate(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Middo Demo Corp',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 50000,
            'address' => 'House 12, Road 5',
        ], $overrides));
    }

    private function makeCityArea(): array
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return [$city, $area];
    }

    private function makeCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE100',
            'name' => 'Save 100',
            'type' => Coupon::TYPE_FIXED,
            'value' => 100,
            'min_subtotal' => 0,
            'per_user_limit' => 1,
            'applies_to' => Coupon::APPLIES_BOTH,
            'is_active' => true,
        ], $overrides));
    }

    public function test_admin_can_create_coupon_via_livewire(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $this->adminRole->id,
            'status' => 'active',
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\CouponTable::class)
            ->call('openCreate')
            ->set('code', 'lunch50')
            ->set('name', 'Lunch 50')
            ->set('type', 'fixed')
            ->set('value', 50)
            ->set('applies_to', 'both')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('coupons', [
            'code' => 'LUNCH50',
            'value' => 50,
            'is_active' => 1,
        ]);
    }

    public function test_coupon_service_quotes_percent_with_cap(): void
    {
        $user = $this->makeCorporate();
        $coupon = $this->makeCoupon([
            'code' => 'PCT20',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 20,
            'max_discount' => 150,
        ]);

        $quoted = app(CouponService::class)->quote('pct20', $user, CouponRedemption::CONTEXT_ORDER, 1000);
        $this->assertSame($coupon->id, $quoted['coupon']->id);
        $this->assertSame(150, $quoted['discount_amount']);
        $this->assertSame(850, $quoted['final_amount']);
    }

    public function test_package_subscribe_applies_coupon_and_records_redemption(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
        ]);
        $menu = MenuItem::create([
            'name' => 'Thali',
            'summary' => 'Lunch',
            'price' => 150,
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $start = now(OrderCutoff::timezone())->startOfMonth();
        $package = MealPackage::create([
            'name' => 'Classic',
            'summary' => 'Rate',
            'price_per_day' => 100,
            'diet_tag' => 'classic',
            'duration_days' => 30,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);
        $this->makeCoupon(['code' => 'PKG100', 'value' => 100, 'applies_to' => Coupon::APPLIES_PACKAGES]);

        $workingDays = \App\Support\PackageBilling::availableDatesInMonth('2026-08', [5, 6])->count();

        $result = app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            '2026-08',
            'Corporate User',
            $user->mobile,
            'House 12',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance',
            null,
            'PKG100'
        );

        /** @var PackageSubscription $subscription */
        $subscription = $result['subscription'];
        $expectedTotal = 150 * $workingDays;
        $this->assertSame($expectedTotal, (int) $subscription->total_amount);
        $this->assertSame(100, (int) $subscription->discount_amount);
        $this->assertSame($expectedTotal - 100, (int) $subscription->amount_paid);
        $this->assertSame(50000 - ($expectedTotal - 100), (int) $user->fresh()->balance);

        $this->assertDatabaseHas('coupon_redemptions', [
            'code' => 'PKG100',
            'user_id' => $user->id,
            'context' => CouponRedemption::CONTEXT_PACKAGE,
            'discount_amount' => 100,
            'package_subscription_id' => $subscription->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_coupon_cannot_be_reused_beyond_per_user_limit(): void
    {
        $user = $this->makeCorporate();
        $coupon = $this->makeCoupon(['code' => 'ONCE', 'per_user_limit' => 1]);

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'code' => 'ONCE',
            'context' => CouponRedemption::CONTEXT_ORDER,
            'original_amount' => 500,
            'discount_amount' => 100,
            'final_amount' => 400,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CouponService::class)->quote('ONCE', $user, CouponRedemption::CONTEXT_ORDER, 500);
    }
}
