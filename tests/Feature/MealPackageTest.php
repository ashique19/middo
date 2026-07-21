<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MealPackageTest extends TestCase
{
    use RefreshDatabase;

    private Role $corporateRole;

    private Role $adminRole;

    private Role $operationRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->operationRole = Role::create(['name' => 'operation']);
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

    private function makeOps(): User
    {
        return User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310123900',
            'password' => '12345678',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
        ]);
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

    private function makeRatePlan(int $pricePerDay = 79): MealPackage
    {
        $start = now(OrderCutoff::timezone())->startOfMonth();

        return MealPackage::create([
            'name' => '৳'.$pricePerDay.'/day Classic',
            'summary' => 'Monthly rate plan',
            'price_per_day' => $pricePerDay,
            'diet_tag' => 'classic',
            'duration_days' => 30,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'status' => MealPackage::STATUS_PUBLISHED,
            'display_order' => 1,
        ]);
    }

    private function workingDays(string $month = '2026-08', array $omitted = [5, 6]): int
    {
        return PackageBilling::availableDatesInMonth($month, $omitted)->count();
    }

    private function subscribeWithSelection(
        User $user,
        MealPackage $package,
        MenuItem $menu,
        City $city,
        Area $area,
        ?int $dayCount = null,
        string $month = '2026-08',
        array $omitted = [5, 6]
    ): array {
        $dayCount ??= $this->workingDays($month, $omitted);

        return app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            $omitted,
            [['menu_item_id' => $menu->id, 'day_count' => $dayCount]],
            $month,
            'Corporate User',
            $user->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );
    }

    private function assignAllDates(User $ops, PackageSubscription $subscription, MenuItem $menu): array
    {
        $available = PackageBilling::availableDatesInMonth(
            (string) $subscription->target_month,
            $subscription->omitted_weekdays ?? []
        );

        $assignments = $available->map(fn ($date) => [
            'date' => $date,
            'menu_item_id' => $menu->id,
        ])->values()->all();

        return app(PackageSubscriptionService::class)->assignSchedule($ops, $subscription, $assignments);
    }

    public function test_selection_quote_must_fill_all_working_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(100);
        $workingDays = $this->workingDays('2026-08');

        $partial = PackageBilling::quoteFromSelections(
            $package,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => 10]],
            [5, 6],
            '2026-08'
        );
        $this->assertFalse($partial['fills_month']);
        $this->assertSame(10, $partial['billable_days']);

        $full = PackageBilling::quoteFromSelections(
            $package,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            [5, 6],
            '2026-08'
        );
        $this->assertTrue($full['fills_month']);
        $this->assertSame($workingDays, $full['billable_days']);
        $this->assertSame($workingDays * 100, $full['total_amount']);
        $this->assertSame('2026-08', $full['target_month']);

        try {
            PackageBilling::assertSelectionsFillMonth($partial);
            $this->fail('Expected partial month selection to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('all '.$workingDays.' working days', $e->getMessage());
        }

        Carbon::setTestNow();
    }

    public function test_corporate_can_subscribe_prepaid_then_ops_schedules_and_skip_refunds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
        ]);
        $ops = $this->makeOps();
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-08');

        $quote = PackageBilling::quoteFromSelections(
            $package,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            [5, 6],
            '2026-08'
        );

        $result = $this->subscribeWithSelection($user, $package, $menu, $city, $area, null, '2026-08');
        $subscription = $result['subscription'];

        $this->assertInstanceOf(PackageSubscription::class, $subscription);
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertSame(PackageSubscription::SCHEDULE_AWAITING, $subscription->schedule_status);
        $this->assertSame($quote['total_amount'], (int) $subscription->total_amount);
        $this->assertSame($workingDays, (int) $subscription->billable_days);
        $this->assertSame(0, Order::where('package_subscription_id', $subscription->id)->count());
        $this->assertSame(1, $subscription->selections()->count());

        $user->refresh();
        $this->assertSame(50000 - $quote['total_amount'], (int) $user->balance);

        $scheduled = $this->assignAllDates($ops, $subscription, $menu);
        $this->assertSame(PackageSubscription::SCHEDULE_SCHEDULED, $scheduled['subscription']->schedule_status);
        $this->assertSame($workingDays, Order::where('package_subscription_id', $subscription->id)->count());

        foreach ($scheduled['orders'] as $order) {
            $dow = Carbon::parse($order->delivery_date)->dayOfWeek;
            $this->assertNotContains($dow, [Carbon::FRIDAY, Carbon::SATURDAY]);
        }

        $order = Order::where('package_subscription_id', $subscription->id)
            ->orderBy('delivery_date')
            ->first();
        $refund = (int) $order->amount_paid;
        app(PackageSubscriptionService::class)->skipDay($user, $order);

        $order->refresh();
        $user->refresh();
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame(50000 - $quote['total_amount'] + $refund, (int) $user->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_REFUND,
            'amount' => $refund,
        ]);

        Carbon::setTestNow();
    }

    public function test_api_lists_packages_and_subscribes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
            'mobile' => '01710123456',
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-08');

        Sanctum::actingAs($user);

        $this->getJson('/api/corporate/packages')
            ->assertOk()
            ->assertJsonPath('packages.0.id', (string) $package->id)
            ->assertJsonStructure(['menus']);

        $otpResponse = $this->postJson('/api/corporate/packages/send-otp', [
            'mobile' => '01710123456',
        ])->assertOk();

        $otp = $otpResponse->json('debug_otp') ?? '1234';

        $response = $this->postJson('/api/corporate/packages/'.$package->id.'/subscribe', [
            'quantity' => 1,
            'target_month' => '2026-08',
            'omitted_weekdays' => [5, 6],
            'menu_selections' => [
                ['menu_item_id' => $menu->id, 'day_count' => $workingDays],
            ],
            'receiver_name' => 'Corporate User',
            'receiver_mobile' => '01710123456',
            'address' => 'House 12, Road 5',
            'city_id' => $city->id,
            'area_id' => $area->id,
            'delivery_time' => '12:00 PM',
            'otp' => $otp,
            'payment_method' => 'balance',
        ]);

        $response->assertCreated()
            ->assertJsonPath('subscription.package.id', (string) $package->id)
            ->assertJsonPath('subscription.schedule_status', PackageSubscription::SCHEDULE_AWAITING)
            ->assertJsonPath('subscription.billable_days', $workingDays);

        $subscriptionId = $response->json('subscription.id');

        $this->getJson('/api/corporate/subscriptions/'.$subscriptionId)
            ->assertOk()
            ->assertJsonPath('subscription.id', (string) $subscriptionId)
            ->assertJsonPath('subscription.selections.0.day_count', $workingDays);

        Carbon::setTestNow();
    }

    public function test_admin_can_open_package_builder(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $this->adminRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.packages.create'))
            ->assertOk();
    }
}
