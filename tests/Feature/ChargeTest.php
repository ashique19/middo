<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Charge;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\PackageSubscription;
use App\Models\PackageSubscriptionCharge;
use App\Models\Role;
use App\Models\User;
use App\Support\ChargeService;
use App\Support\PackageSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChargeTest extends TestCase
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

    private function makeAdmin(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $this->adminRole->id,
            'status' => 'active',
        ]);
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
            'balance' => 100000,
            'address' => 'House 12, Road 5',
        ], $overrides));
    }

    private function makeCityArea(): array
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return [$city, $area];
    }

    private function makeMenuItem(string $name = 'Office Thali', int $price = 200): MenuItem
    {
        return MenuItem::create([
            'name' => $name,
            'price' => $price,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_scoped_charge(): void
    {
        $admin = $this->makeAdmin();
        [$city, $area] = $this->makeCityArea();
        $menu = $this->makeMenuItem();

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\ChargeTable::class)
            ->call('openCreate')
            ->set('name', 'Premium packaging')
            ->set('category', Charge::CATEGORY_PACKAGING)
            ->set('amount', 25)
            ->set('calculation', Charge::CALC_PER_ITEM)
            ->set('area_id', $area->id)
            ->set('menu_item_id', $menu->id)
            ->set('applies_to', Charge::APPLIES_ORDERS)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('charges', [
            'name' => 'Premium packaging',
            'amount' => 25,
            'area_id' => $area->id,
            'menu_item_id' => $menu->id,
            'calculation' => Charge::CALC_PER_ITEM,
        ]);
    }

    public function test_order_cart_applies_delivery_and_packaging_by_area_and_menu(): void
    {
        [$city, $area] = $this->makeCityArea();
        $otherArea = Area::create(['name' => 'Banani', 'city_id' => $city->id]);
        $menu = $this->makeMenuItem();
        $otherMenu = $this->makeMenuItem('Veg Bowl', 180);

        Charge::create([
            'name' => 'Delivery',
            'category' => Charge::CATEGORY_DELIVERY,
            'amount' => 40,
            'calculation' => Charge::CALC_PER_DELIVERY,
            'area_id' => $area->id,
            'menu_item_id' => null,
            'applies_to' => Charge::APPLIES_ORDERS,
            'is_active' => true,
        ]);

        Charge::create([
            'name' => 'Premium packaging',
            'category' => Charge::CATEGORY_PACKAGING,
            'amount' => 15,
            'calculation' => Charge::CALC_PER_ITEM,
            'area_id' => $area->id,
            'menu_item_id' => $menu->id,
            'applies_to' => Charge::APPLIES_BOTH,
            'is_active' => true,
        ]);

        Charge::create([
            'name' => 'Wrong area delivery',
            'category' => Charge::CATEGORY_DELIVERY,
            'amount' => 99,
            'calculation' => Charge::CALC_PER_DELIVERY,
            'area_id' => $otherArea->id,
            'applies_to' => Charge::APPLIES_ORDERS,
            'is_active' => true,
        ]);

        $quote = app(ChargeService::class)->quoteOrderCart($area->id, $menu->id, [
            '2026-08-03' => 2,
            '2026-08-04' => 1,
        ]);

        // 2 delivery dates * 40 + (2+1 items) * 15 packaging
        $this->assertSame(80 + 45, $quote['total']);
        $this->assertCount(2, $quote['lines']);

        $wrongMenu = app(ChargeService::class)->quoteOrderCart($area->id, $otherMenu->id, [
            '2026-08-03' => 2,
        ]);
        $this->assertSame(40, $wrongMenu['total']);
    }

    public function test_package_subscribe_persists_charge_lines(): void
    {
        [$city, $area] = $this->makeCityArea();
        $corporate = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
        ]);
        $menu = $this->makeMenuItem();

        $package = MealPackage::create([
            'name' => 'Monthly Office',
            'summary' => 'Test',
            'price_per_day' => 150,
            'diet_tag' => 'regular',
            'duration_days' => 22,
            'start_date' => now('Asia/Dhaka')->startOfMonth()->toDateString(),
            'end_date' => now('Asia/Dhaka')->startOfMonth()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
            'created_by' => $corporate->id,
        ]);

        Charge::create([
            'name' => 'Handling',
            'category' => Charge::CATEGORY_HANDLING,
            'amount' => 100,
            'calculation' => Charge::CALC_PER_CHECKOUT,
            'area_id' => null,
            'menu_item_id' => null,
            'applies_to' => Charge::APPLIES_PACKAGES,
            'is_active' => true,
        ]);

        Charge::create([
            'name' => 'Delivery',
            'category' => Charge::CATEGORY_DELIVERY,
            'amount' => 30,
            'calculation' => Charge::CALC_PER_DELIVERY,
            'area_id' => $area->id,
            'menu_item_id' => null,
            'applies_to' => Charge::APPLIES_BOTH,
            'is_active' => true,
        ]);

        $targetMonth = now('Asia/Dhaka')->startOfMonth()->addMonth()->format('Y-m');
        $workingDays = \App\Support\PackageBilling::availableDatesInMonth($targetMonth, [5, 6])->count();
        $this->assertGreaterThan(0, $workingDays);

        $result = app(PackageSubscriptionService::class)->subscribe(
            $corporate,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            $targetMonth,
            'Receiver Name',
            '01710123456',
            'House 1',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance',
            null
        );

        /** @var PackageSubscription $subscription */
        $subscription = $result['subscription'];
        $food = 200 * $workingDays;
        $fees = 100 + (30 * $workingDays);

        // total_amount stores pre-discount merchandise only; charges_amount stores charges separately.
        $this->assertSame($food, (int) $subscription->total_amount);
        $this->assertSame($fees, (int) $subscription->charges_amount);
        // amount_paid = max(0, food - discount) + charges = food + fees (no coupon in this test)
        $this->assertSame($food + $fees, (int) $subscription->amount_paid);
        $this->assertSame(2, PackageSubscriptionCharge::query()->where('package_subscription_id', $subscription->id)->count());
    }

    public function test_inactive_charge_is_ignored(): void
    {
        [$city, $area] = $this->makeCityArea();
        $menu = $this->makeMenuItem();

        Charge::create([
            'name' => 'Off delivery',
            'category' => Charge::CATEGORY_DELIVERY,
            'amount' => 50,
            'calculation' => Charge::CALC_PER_DELIVERY,
            'area_id' => $area->id,
            'applies_to' => Charge::APPLIES_BOTH,
            'is_active' => false,
        ]);

        $quote = app(ChargeService::class)->quoteOrderCart($area->id, $menu->id, [
            '2026-08-03' => 1,
        ]);

        $this->assertSame(0, $quote['total']);
    }

    public function test_order_charge_lines_can_be_attached(): void
    {
        [$city, $area] = $this->makeCityArea();
        $corporate = $this->makeCorporate(['city_id' => $city->id, 'area_id' => $area->id]);
        $menu = $this->makeMenuItem();

        $charge = Charge::create([
            'name' => 'Delivery',
            'category' => Charge::CATEGORY_DELIVERY,
            'amount' => 40,
            'calculation' => Charge::CALC_PER_DELIVERY,
            'applies_to' => Charge::APPLIES_ORDERS,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 240,
            'charges_amount' => 40,
            'amount_paid' => 0,
            'prepaid_amount' => 0,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'area_id' => $area->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $sum = app(ChargeService::class)->attachToOrder($order, [[
            'charge_id' => $charge->id,
            'name' => 'Delivery',
            'category' => 'delivery',
            'calculation' => 'per_delivery',
            'unit_amount' => 40,
            'quantity' => 1,
            'amount' => 40,
        ]]);

        $this->assertSame(40, $sum);
        $this->assertSame(1, OrderCharge::query()->where('order_id', $order->id)->count());
    }
}
