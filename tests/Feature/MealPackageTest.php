<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MealPackageDay;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\OrderConfirmationOtp;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->adminRole = Role::create(['name' => 'admin']);
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

    private function makeMenuItem(int $price = 150): MenuItem
    {
        return MenuItem::create([
            'name' => 'Office Thali',
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

    public function test_package_billing_omits_friday_and_saturday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone())); // Monday

        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 100, 14);

        $quote = PackageBilling::quote($package, 1, [5, 6]); // omit Fri/Sat

        foreach ($quote['days'] as $day) {
            $dow = Carbon::parse($day['date'])->dayOfWeek;
            $this->assertNotContains($dow, [Carbon::FRIDAY, Carbon::SATURDAY]);
        }

        $this->assertSame(100 * $quote['billable_days'], $quote['total_amount']);
        $this->assertLessThan(14, $quote['billable_days']);

        Carbon::setTestNow();
    }

    public function test_corporate_can_subscribe_package_with_balance_and_skip_day_for_refund(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makePublishedPackage($menu, 79, 10);

        $quote = PackageBilling::quote($package, 1, [5, 6]);
        $this->assertGreaterThan(0, $quote['billable_days']);

        $result = app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            [5, 6],
            [],
            'Corporate User',
            '01310123452',
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );

        $subscription = $result['subscription'];
        $this->assertInstanceOf(PackageSubscription::class, $subscription);
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertSame($quote['total_amount'], (int) $subscription->total_amount);
        $this->assertSame($quote['billable_days'], Order::where('package_subscription_id', $subscription->id)->count());

        $user->refresh();
        $this->assertSame(50000 - $quote['total_amount'], (int) $user->balance);

        $order = Order::where('package_subscription_id', $subscription->id)
            ->orderBy('delivery_date')
            ->first();
        $this->assertNotNull($order);

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
        $package = $this->makePublishedPackage($menu, 79, 8);

        Sanctum::actingAs($user);

        $this->getJson('/api/corporate/packages')
            ->assertOk()
            ->assertJsonPath('packages.0.id', (string) $package->id);

        $otpResponse = $this->postJson('/api/corporate/packages/send-otp', [
            'mobile' => '01710123456',
        ])->assertOk();

        $otp = $otpResponse->json('debug_otp') ?? '1234';

        $response = $this->postJson('/api/corporate/packages/'.$package->id.'/subscribe', [
            'quantity' => 1,
            'omitted_weekdays' => [5, 6],
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
            ->assertJsonPath('subscription.package.id', (string) $package->id);

        $subscriptionId = $response->json('subscription.id');
        $orderId = $response->json('subscription.orders.0.id');

        $this->postJson('/api/corporate/orders/'.$orderId.'/skip-package-day')
            ->assertOk()
            ->assertJsonPath('order.order_status', 'cancelled');

        $this->getJson('/api/corporate/subscriptions/'.$subscriptionId)
            ->assertOk()
            ->assertJsonPath('subscription.id', (string) $subscriptionId);

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
