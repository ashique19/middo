<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MealPackageDay;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroupOrder;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageOrderPresenter;
use App\Support\PackageSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageOpsManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $corporateRole;

    private Role $adminRole;

    private Role $operationRole;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->operationRole = Role::create(['name' => 'operation']);
        $this->kitchenRole = Role::create(['name' => 'kitchen']);
    }

    private function makeUser(Role $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => ucfirst($role->name),
            'last_name' => 'User',
            'company_name' => $role->name === 'corporate' ? 'Middo Demo Corp' : null,
            'mobile' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $role->id,
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

    private function makeMenuItem(string $name = 'Office Thali', int $price = 150): MenuItem
    {
        return MenuItem::create([
            'name' => $name,
            'summary' => 'Daily lunch',
            'price' => $price,
            'is_featured' => true,
            'display_order' => 1,
        ]);
    }

    private function makePublishedPackage(MenuItem $menuItem, int $pricePerDay = 79, int $days = 10): MealPackage
    {
        $start = now(OrderCutoff::timezone())->addDay()->startOfDay();
        $package = MealPackage::create([
            'name' => '৳'.$pricePerDay.'/day Classic',
            'summary' => 'Month plan',
            'price_per_day' => $pricePerDay,
            'diet_tag' => 'classic',
            'duration_days' => $days,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays($days - 1)->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);

        for ($i = 0; $i < $days; $i++) {
            MealPackageDay::create([
                'meal_package_id' => $package->id,
                'delivery_date' => $start->copy()->addDays($i)->toDateString(),
                'menu_item_id' => $menuItem->id,
            ]);
        }

        return $package->fresh('days');
    }

    private function subscribeCorporate(User $user, MealPackage $package, City $city, Area $area): array
    {
        return app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            [5, 6],
            [],
            'Corporate User',
            $user->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );
    }

    public function test_package_order_presenter_and_auto_group_visibility(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123999',
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 8);

        $result = $this->subscribeCorporate($user, $package, $city, $area);
        $order = $result['orders']->first();

        $fields = PackageOrderPresenter::fields($order->fresh('packageSubscription.package'));
        $this->assertTrue($fields['is_package']);
        $this->assertSame($package->name, $fields['package_name']);
        $this->assertNotNull($fields['package_subscription_id']);
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $order->id)->exists());

        Carbon::setTestNow();
    }

    public function test_staff_skip_day_ungroups_and_refunds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123998',
            'balance' => 50000,
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123997', 'balance' => 0]);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 100, 8);
        $quote = PackageBilling::quote($package, 1, [5, 6]);

        $result = $this->subscribeCorporate($user, $package, $city, $area);
        $order = Order::query()->where('package_subscription_id', $result['subscription']->id)->orderBy('delivery_date')->first();
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $order->id)->exists());

        $refund = (int) $order->amount_paid;
        app(PackageSubscriptionService::class)->skipDayAsStaff($ops, $order);

        $order->refresh();
        $user->refresh();
        $this->assertSame('cancelled', $order->order_status);
        $this->assertFalse(OrderGroupOrder::query()->where('order_id', $order->id)->exists());
        $this->assertSame(50000 - $quote['total_amount'] + $refund, (int) $user->balance);

        Carbon::setTestNow();
    }

    public function test_cancel_remaining_and_swap_menu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123996',
            'balance' => 50000,
        ]);
        $admin = $this->makeUser($this->adminRole, ['mobile' => '01310123995']);
        $menuA = $this->makeMenuItem('Menu A');
        $menuB = $this->makeMenuItem('Menu B', 180);
        $package = $this->makePublishedPackage($menuA, 79, 8);

        $result = $this->subscribeCorporate($user, $package, $city, $area);
        $subscription = $result['subscription'];
        $order = Order::query()->where('package_subscription_id', $subscription->id)->orderBy('delivery_date')->first();

        $swapped = app(PackageSubscriptionService::class)->swapDayMenu($admin, $order, $menuB->id);
        $this->assertSame($menuB->id, (int) $swapped->menu_item_id);
        $this->assertTrue(OrderGroupOrder::query()->where('order_id', $swapped->id)->exists());

        $beforeBalance = (int) $user->fresh()->balance;
        $cancel = app(PackageSubscriptionService::class)->cancelRemaining($admin, $subscription->fresh());
        $this->assertGreaterThan(0, $cancel['cancelled_orders']);
        $this->assertSame(PackageSubscription::STATUS_CANCELLED, $cancel['subscription']->status);
        $this->assertSame($beforeBalance + $cancel['refunded_amount'], (int) $user->fresh()->balance);

        Carbon::setTestNow();
    }

    public function test_admin_and_operation_can_open_new_package_pages(): void
    {
        $admin = $this->makeUser($this->adminRole, ['mobile' => '01310123994']);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123993']);
        $kitchen = $this->makeUser($this->kitchenRole, ['mobile' => '01310123992']);

        $this->actingAs($admin)->get(route('admin.subscriptions.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.packages.demand'))->assertOk();
        $this->actingAs($admin)->get(route('admin.packages.insights'))->assertOk();

        $this->actingAs($ops)->get(route('operation.subscriptions.index'))->assertOk();
        $this->actingAs($ops)->get(route('operation.packages.create'))->assertOk();
        $this->actingAs($ops)->get(route('operation.packages.demand'))->assertOk();

        $this->actingAs($kitchen)->get(route('kitchen.menus.today'))->assertOk();
    }

    public function test_operation_cannot_force_complete_but_admin_can(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123991',
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123990']);
        $admin = $this->makeUser($this->adminRole, ['mobile' => '01310123989']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 6);
        $subscription = $this->subscribeCorporate($user, $package, $city, $area)['subscription'];

        $this->expectException(\RuntimeException::class);
        app(PackageSubscriptionService::class)->forceComplete($ops, $subscription);

        Carbon::setTestNow();
    }

    public function test_admin_force_complete_subscription(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123988',
        ]);
        $admin = $this->makeUser($this->adminRole, ['mobile' => '01310123987']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 6);
        $subscription = $this->subscribeCorporate($user, $package, $city, $area)['subscription'];

        $completed = app(PackageSubscriptionService::class)->forceComplete($admin, $subscription);
        $this->assertSame(PackageSubscription::STATUS_COMPLETED, $completed->status);

        Carbon::setTestNow();
    }

    public function test_bulk_skip_date_and_clone_package_route(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123986',
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123985']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 8);
        $subscription = $this->subscribeCorporate($user, $package, $city, $area)['subscription'];
        $firstDate = Order::query()
            ->where('package_subscription_id', $subscription->id)
            ->orderBy('delivery_date')
            ->value('delivery_date');

        $result = app(PackageSubscriptionService::class)->bulkSkipDate($ops, Carbon::parse($firstDate)->toDateString());
        $this->assertGreaterThan(0, $result['skipped']);

        $this->actingAs($ops)
            ->get(route('operation.subscriptions.show', $subscription->id))
            ->assertOk()
            ->assertSee('Subscription #'.$subscription->id);

        Carbon::setTestNow();
    }

    public function test_package_filter_on_active_orders_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeUser($this->corporateRole, [
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123984',
        ]);
        $ops = $this->makeUser($this->operationRole, ['mobile' => '01310123983']);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 6);
        $this->subscribeCorporate($user, $package, $city, $area);

        $this->actingAs($ops)
            ->get(route('operation.orders.active'))
            ->assertOk()
            ->assertSee('Package only');

        Carbon::setTestNow();
    }
}
