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

    public function test_waive_charge_coupon_quotes_matching_delivery_fees(): void
    {
        $user = $this->makeCorporate();
        $this->makeCoupon([
            'code' => 'FREEDEL',
            'type' => Coupon::TYPE_WAIVE_CHARGE,
            'value' => 0,
            'waive_charge_category' => 'delivery',
            'max_discount' => null,
        ]);

        $quoted = app(CouponService::class)->quote(
            'FREEDEL',
            $user,
            CouponRedemption::CONTEXT_ORDER,
            1500,
            [
                'charge_lines' => [
                    ['charge_id' => 1, 'category' => 'delivery', 'amount' => 80],
                    ['charge_id' => 2, 'category' => 'handling', 'amount' => 40],
                ],
            ]
        );

        $this->assertSame(80, $quoted['discount_amount']);
    }

    public function test_waive_charge_respects_max_discount_cap(): void
    {
        $user = $this->makeCorporate();
        $this->makeCoupon([
            'code' => 'CAPDEL',
            'type' => Coupon::TYPE_WAIVE_CHARGE,
            'value' => 0,
            'waive_charge_category' => 'delivery',
            'max_discount' => 50,
        ]);

        $quoted = app(CouponService::class)->quote(
            'CAPDEL',
            $user,
            CouponRedemption::CONTEXT_ORDER,
            1500,
            ['charge_lines' => [['charge_id' => 1, 'category' => 'delivery', 'amount' => 120]]]
        );

        $this->assertSame(50, $quoted['discount_amount']);
    }

    public function test_menu_area_and_min_quantity_eligibility(): void
    {
        [$city, $area] = $this->makeCityArea();
        $otherArea = Area::create(['name' => 'Banani', 'city_id' => $city->id]);
        $menu = MenuItem::create([
            'name' => 'Thali',
            'summary' => 'Lunch',
            'price' => 150,
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $user = $this->makeCorporate(['area_id' => $area->id, 'city_id' => $city->id]);

        $this->makeCoupon([
            'code' => 'SCOPED',
            'type' => Coupon::TYPE_FIXED,
            'value' => 50,
            'eligibility' => [
                'menu_item_ids' => [$menu->id],
                'area_ids' => [$area->id],
                'company_ids' => [],
                'first_order_only' => false,
                'min_quantity' => 10,
            ],
        ]);

        $ok = app(CouponService::class)->quote('SCOPED', $user, CouponRedemption::CONTEXT_ORDER, 2000, [
            'area_id' => $area->id,
            'menu_item_ids' => [$menu->id],
            'quantity' => 12,
        ]);
        $this->assertSame(50, $ok['discount_amount']);

        try {
            app(CouponService::class)->quote('SCOPED', $user, CouponRedemption::CONTEXT_ORDER, 2000, [
                'area_id' => $otherArea->id,
                'menu_item_ids' => [$menu->id],
                'quantity' => 12,
            ]);
            $this->fail('Expected area eligibility failure');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('delivery area', $e->validator->errors()->first());
        }

        try {
            app(CouponService::class)->quote('SCOPED', $user, CouponRedemption::CONTEXT_ORDER, 2000, [
                'area_id' => $area->id,
                'menu_item_ids' => [$menu->id],
                'quantity' => 5,
            ]);
            $this->fail('Expected min quantity failure');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('at least 10', $e->validator->errors()->first());
        }
    }

    public function test_first_order_only_blocks_repeat_customers(): void
    {
        $user = $this->makeCorporate();
        $this->makeCoupon([
            'code' => 'WELCOME',
            'type' => Coupon::TYPE_FIXED,
            'value' => 100,
            'eligibility' => [
                'menu_item_ids' => [],
                'area_ids' => [],
                'company_ids' => [],
                'first_order_only' => true,
                'min_quantity' => null,
            ],
        ]);

        $first = app(CouponService::class)->quote('WELCOME', $user, CouponRedemption::CONTEXT_ORDER, 500);
        $this->assertSame(100, $first['discount_amount']);

        $menu = MenuItem::create([
            'name' => 'Prior',
            'summary' => 'Prior',
            'price' => 200,
            'is_featured' => true,
            'display_order' => 1,
        ]);
        \App\Models\Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'House 12, Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CouponService::class)->quote('WELCOME', $user, CouponRedemption::CONTEXT_ORDER, 500);
    }

    public function test_admin_can_create_waive_charge_coupon_with_scopes(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $this->adminRole->id,
            'status' => 'active',
        ]);
        [$city, $area] = $this->makeCityArea();
        $menu = MenuItem::create([
            'name' => 'Thali',
            'summary' => 'Lunch',
            'price' => 150,
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $charge = \App\Models\Charge::create([
            'name' => 'Delivery',
            'category' => 'delivery',
            'amount' => 50,
            'calculation' => 'per_delivery',
            'applies_to' => 'both',
            'is_active' => true,
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\CouponTable::class)
            ->call('openCreate')
            ->set('code', 'free-ship')
            ->set('name', 'Free shipping')
            ->set('type', 'waive_charge')
            ->set('waive_charge_category', 'delivery')
            ->set('waive_charge_id', $charge->id)
            ->set('max_discount', 100)
            ->set('eligible_menu_item_ids', [$menu->id])
            ->set('eligible_area_ids', [$area->id])
            ->set('first_order_only', true)
            ->set('min_quantity', 5)
            ->call('save')
            ->assertSet('showForm', false)
            ->assertHasNoErrors();

        $coupon = Coupon::query()->where('code', 'FREE-SHIP')->first();
        $this->assertNotNull($coupon);
        $this->assertSame(Coupon::TYPE_WAIVE_CHARGE, $coupon->type);
        $this->assertSame('delivery', $coupon->waive_charge_category);
        $this->assertSame($charge->id, (int) $coupon->waive_charge_id);
        $this->assertTrue($coupon->firstOrderOnly());
        $this->assertSame(5, $coupon->minQuantity());
        $this->assertSame([$menu->id], $coupon->eligibleMenuItemIds());
        $this->assertSame([$area->id], $coupon->eligibleAreaIds());
    }

    public function test_package_subscribe_waive_charge_reduces_payable_not_food(): void
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
        \App\Models\Charge::create([
            'name' => 'Pkg delivery',
            'category' => 'delivery',
            'amount' => 20,
            'calculation' => 'per_delivery',
            'applies_to' => 'packages',
            'is_active' => true,
        ]);
        $this->makeCoupon([
            'code' => 'WAIVEPKG',
            'type' => Coupon::TYPE_WAIVE_CHARGE,
            'value' => 0,
            'waive_charge_category' => 'delivery',
            'applies_to' => Coupon::APPLIES_PACKAGES,
        ]);

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
            'WAIVEPKG'
        );

        /** @var PackageSubscription $subscription */
        $subscription = $result['subscription'];
        $food = 150 * $workingDays;
        $charges = 20 * $workingDays;
        $this->assertSame($food, (int) $subscription->total_amount);
        $this->assertSame($charges, (int) $subscription->discount_amount);
        $this->assertSame($food, (int) $subscription->amount_paid);

        Carbon::setTestNow();
    }
}
