<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\Coupon;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageRefund;
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
        $this->assertSame($workingDays * 150, $full['total_amount']); // menu price × days × qty
        $this->assertSame(150, $full['selections'][0]['unit_price']);
        $this->assertSame('2026-08', $full['target_month']);

        try {
            PackageBilling::assertSelectionsFillMonth($partial);
            $this->fail('Expected partial month selection to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('all '.$workingDays.' working days', $e->getMessage());
        }

        Carbon::setTestNow();
    }

    public function test_subscribe_modal_cannot_select_more_than_working_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
        ]);
        $menuA = $this->makeMenuItem('Menu A');
        $menuB = $this->makeMenuItem('Menu B', 160);
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-07');

        $this->assertSame(7, $workingDays);

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Corporate\PackageSubscribeModal::class)
            ->call('open', $package->id)
            ->assertSet('workingDays', $workingDays)
            ->assertSet('selectedDays', 0);

        for ($i = 0; $i < $workingDays; $i++) {
            $component->call('changeMenuDays', $menuA->id, 1);
        }

        $component
            ->assertSet('selectedDays', $workingDays)
            ->assertSet('fillsMonth', true)
            ->call('changeMenuDays', $menuB->id, 1)
            ->assertSet('selectedDays', $workingDays)
            ->assertSet('menuDayCounts.'.((string) $menuB->id), null);

        $this->assertStringContainsString('all '.$workingDays.' working days', $component->get('errorMessage'));

        Carbon::setTestNow();
    }

    public function test_subscribe_modal_hides_wallet_pay_when_balance_is_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 0,
        ]);
        $menu = $this->makeMenuItem('Menu A');
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-07');

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Corporate\PackageSubscribeModal::class)
            ->call('open', $package->id);

        for ($i = 0; $i < $workingDays; $i++) {
            $component->call('changeMenuDays', $menu->id, 1);
        }

        $component
            ->assertSet('fillsMonth', true)
            ->assertSet('walletBalance', 0)
            ->assertDontSee('Pay with Middo wallet')
            ->assertSee('Pay online')
            ->assertDontSee('Pay online instead')
            ->assertDontSee('Confirm & pay with wallet');

        Carbon::setTestNow();
    }

    public function test_subscribe_modal_shows_wallet_pay_when_balance_covers_total(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
        ]);
        $menu = $this->makeMenuItem('Menu A');
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-07');

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Corporate\PackageSubscribeModal::class)
            ->call('open', $package->id);

        for ($i = 0; $i < $workingDays; $i++) {
            $component->call('changeMenuDays', $menu->id, 1);
        }

        $component
            ->assertSet('fillsMonth', true)
            ->assertSee('Pay with Middo wallet')
            ->assertSee('Pay online')
            ->assertDontSee('Pay online instead')
            ->assertDontSee('Confirm & pay with wallet');

        Carbon::setTestNow();
    }

    public function test_subscribe_modal_shows_validation_errors_in_sticky_feedback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 0,
        ]);
        $menu = $this->makeMenuItem('Menu A');
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-07');

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Corporate\PackageSubscribeModal::class)
            ->call('open', $package->id)
            ->set('customerName', '')
            ->set('mobile', '')
            ->set('addressLine1', '');

        for ($i = 0; $i < $workingDays; $i++) {
            $component->call('changeMenuDays', $menu->id, 1);
        }

        $component
            ->call('startGatewayPayment')
            ->assertSet('errorMessage', fn ($message) => filled($message))
            ->assertSeeHtml('id="pkg-modal-feedback"')
            ->assertSee($component->get('errorMessage'));

        Carbon::setTestNow();
    }

    public function test_pay_online_verifies_otp_then_payment_creates_package(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 0,
            'mobile' => '01710123123',
        ]);
        $menu = $this->makeMenuItem('Menu A');
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-07');

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Corporate\PackageSubscribeModal::class)
            ->call('open', $package->id)
            ->set('customerName', 'Dev Signature')
            ->set('mobile', '01710123123')
            ->set('addressLine1', 'Mirpur dhaka')
            ->set('city_id', $city->id)
            ->set('area_id', $area->id);

        for ($i = 0; $i < $workingDays; $i++) {
            $component->call('changeMenuDays', $menu->id, 1);
        }

        $component
            ->call('startGatewayPayment')
            ->assertSet('isConfirmingOtp', true)
            ->assertSet('otpVerified', false)
            ->assertSee('Verify code')
            ->assertDontSeeHtml('data-testid="package-make-payment"');

        \App\Support\OrderConfirmationOtp::generate('01710123123');

        $component
            ->set('otpInput', '1234')
            ->call('verifyGatewayOtp')
            ->assertSet('otpVerified', true)
            ->assertSeeHtml('data-testid="package-make-payment"')
            ->assertSeeHtml('data-testid="package-waiting-payment"');

        $token = $component->get('gatewayPaymentToken');
        $this->assertNotEmpty($token);

        $gateway = app(\App\Contracts\PaymentGateway::class);
        $payload = $gateway->find($token);
        $this->assertIsArray($payload);
        $this->assertSame(\App\Support\PackageGatewayCheckout::PURPOSE, $payload['metadata']['purpose'] ?? null);
        $this->assertFalse((bool) ($payload['paid'] ?? false));

        $confirmUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'corporate.gateway-prepay.confirm',
            now()->addMinutes(45),
            ['token' => $token]
        );
        $this->actingAs($user)
            ->post($confirmUrl)
            ->assertRedirect();

        $subscription = PackageSubscription::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($subscription);
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertSame($package->id, (int) $subscription->meal_package_id);

        // Modal poll is idempotent after redirect completion.
        $again = \App\Support\PackageGatewayCheckout::completeIfPaid($token);
        $this->assertTrue($again['ok'] ?? false);
        $this->assertTrue($again['already_done'] ?? false);

        Carbon::setTestNow();
    }

    public function test_corporate_package_show_accepts_subscription_id_route_param(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);
        $result = $this->subscribeWithSelection($user, $package, $menu, $city, $area, null, '2026-08');
        $subscription = $result['subscription'];

        $this->actingAs($user)
            ->get(route('corporates.packages.show', ['subscriptionId' => $subscription->id]))
            ->assertOk()
            ->assertSee($package->name);

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

    public function test_discounted_package_skip_refunds_allocated_net_amount_via_api(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
            'mobile' => '01710123555',
        ]);
        $ops = $this->makeOps();
        $menu = $this->makeMenuItem('Discounted Package Meal', 150);
        $package = $this->makeRatePlan(100);
        Coupon::create([
            'code' => 'PKG100',
            'name' => 'Package Save 100',
            'type' => Coupon::TYPE_FIXED,
            'value' => 100,
            'min_subtotal' => 0,
            'per_user_limit' => 1,
            'applies_to' => Coupon::APPLIES_PACKAGES,
            'is_active' => true,
        ]);
        $workingDays = $this->workingDays('2026-08');

        $result = app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            '2026-08',
            'Corporate User',
            $user->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance',
            null,
            'PKG100'
        );
        $subscription = $result['subscription'];
        $this->assertSame((150 * $workingDays) - 100, (int) $subscription->amount_paid);

        $scheduled = $this->assignAllDates($ops, $subscription, $menu);
        $order = $scheduled['orders']->first();
        $expectedRefund = PackageRefund::orderRefundAmount($order->fresh('packageSubscription.orders'));
        $beforeBalance = (int) $user->fresh()->balance;

        $this->assertLessThan((int) $order->amount_paid, $expectedRefund);

        Sanctum::actingAs($user);
        $this->postJson('/api/corporate/orders/'.$order->id.'/skip-package-day')
            ->assertOk()
            ->assertJsonPath('refunded_amount', $expectedRefund);

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame($beforeBalance + $expectedRefund, (int) $user->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_REFUND,
            'amount' => $expectedRefund,
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
            ->assertJsonPath('ordered_months', [])
            ->assertJsonStructure(['menus', 'ordered_months']);

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

        $this->getJson('/api/corporate/packages')
            ->assertOk()
            ->assertJsonPath('ordered_months.0', '2026-08');

        Carbon::setTestNow();
    }

    public function test_corporate_cannot_order_second_package_for_same_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 100000,
        ]);
        $menu = $this->makeMenuItem();
        $packageA = $this->makeRatePlan(79);
        $packageB = $this->makeRatePlan(99);

        $this->subscribeWithSelection($user, $packageA, $menu, $city, $area, null, '2026-08');

        try {
            $this->subscribeWithSelection($user, $packageB, $menu, $city, $area, null, '2026-08');
            $this->fail('Expected duplicate-month subscribe to throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already ordered a package for August 2026', $e->getMessage());
        }

        Carbon::setTestNow();
    }

    public function test_subscribe_modal_marks_ordered_months_as_locked(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 50000,
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);

        $this->subscribeWithSelection($user, $package, $menu, $city, $area, null, '2026-07');

        $component = \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Corporate\PackageSubscribeModal::class)
            ->call('open', $package->id)
            ->assertSee('Package ordered');

        $options = collect($component->get('monthOptions'));
        $july = $options->firstWhere('value', '2026-07');
        $this->assertNotNull($july);
        $this->assertTrue((bool) ($july['locked'] ?? false));
        $this->assertNotSame('2026-07', $component->get('targetMonth'));

        $before = $component->get('targetMonth');
        $component
            ->call('selectMonth', '2026-07')
            ->assertSet('targetMonth', $before);

        $this->assertStringContainsString('already ordered a package', $component->get('errorMessage'));

        Carbon::setTestNow();
    }

    public function test_ops_can_confirm_partial_package_days_then_finish_later(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 100000,
        ]);
        $ops = $this->makeOps();
        $menuA = $this->makeMenuItem('Menu A', 200);
        $menuB = $this->makeMenuItem('Menu B', 120);
        $package = $this->makeRatePlan(0);
        $workingDays = $this->workingDays('2026-08');
        $this->assertGreaterThan(5, $workingDays);

        $result = app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            2,
            [5, 6],
            [
                ['menu_item_id' => $menuA->id, 'day_count' => 3],
                ['menu_item_id' => $menuB->id, 'day_count' => $workingDays - 3],
            ],
            '2026-08',
            'Corporate User',
            $user->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );
        $subscription = $result['subscription'];
        $expectedFood = (200 * 3 * 2) + (120 * ($workingDays - 3) * 2);
        $this->assertSame($expectedFood, (int) $subscription->total_amount);
        $this->assertSame(200, (int) $subscription->selections()->where('menu_item_id', $menuA->id)->value('unit_price'));

        $available = PackageBilling::availableDatesInMonth('2026-08', [5, 6])->values();
        $firstBatch = [
            ['date' => $available[0], 'menu_item_id' => $menuA->id],
            ['date' => $available[1], 'menu_item_id' => $menuA->id],
        ];
        $partial = app(PackageSubscriptionService::class)->assignSchedule($ops, $subscription, $firstBatch);
        $this->assertSame(PackageSubscription::SCHEDULE_PARTIAL, $partial['subscription']->schedule_status);
        $this->assertSame(2, $partial['orders']->count());
        $this->assertSame(400, (int) $partial['orders']->first()->total_amount); // 200 × qty 2

        $remainingDates = $available->slice(2)->values();
        $secondBatch = $remainingDates->map(function ($date, $index) use ($menuA, $menuB) {
            return [
                'date' => $date,
                'menu_item_id' => $index === 0 ? $menuA->id : $menuB->id,
            ];
        })->all();

        $done = app(PackageSubscriptionService::class)->assignSchedule(
            $ops,
            $partial['subscription'],
            $secondBatch
        );
        $this->assertSame(PackageSubscription::SCHEDULE_SCHEDULED, $done['subscription']->schedule_status);
        $this->assertSame($workingDays, Order::where('package_subscription_id', $subscription->id)->where('order_status', '!=', 'cancelled')->count());

        Carbon::setTestNow();
    }

    public function test_cancel_remaining_refunds_unscheduled_prepaid_days_on_partial_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'balance' => 100000,
            'mobile' => '01710123666',
        ]);
        $ops = $this->makeOps();
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $this->adminRole->id,
            'status' => 'active',
        ]);
        $menu = $this->makeMenuItem('Partial Cancel Meal', 200);
        $package = $this->makeRatePlan(0);
        $workingDays = $this->workingDays('2026-08');
        $this->assertGreaterThan(3, $workingDays);

        $result = app(PackageSubscriptionService::class)->subscribe(
            $user,
            $package,
            1,
            [5, 6],
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            '2026-08',
            'Corporate User',
            $user->mobile,
            'House 12, Road 5',
            $city->id,
            $area->id,
            '12:00 PM',
            'balance'
        );
        $subscription = $result['subscription'];
        $prepaid = PackageRefund::subscriptionPrepaidAmount($subscription);
        $this->assertSame(200 * $workingDays, $prepaid);
        $balanceAfterSubscribe = (int) $user->fresh()->balance;

        $available = PackageBilling::availableDatesInMonth('2026-08', [5, 6])->values();
        $partial = app(PackageSubscriptionService::class)->assignSchedule($ops, $subscription, [
            ['date' => $available[0], 'menu_item_id' => $menu->id],
            ['date' => $available[1], 'menu_item_id' => $menu->id],
        ]);
        $this->assertSame(PackageSubscription::SCHEDULE_PARTIAL, $partial['subscription']->schedule_status);

        $scheduledRefundValue = (int) array_sum(PackageRefund::packageDayRefundAllocations($partial['subscription']));
        $expectedResidual = PackageRefund::unscheduledPrepaidResidual($partial['subscription']);
        $this->assertSame(200 * ($workingDays - 2), $expectedResidual);
        $this->assertSame($prepaid, $scheduledRefundValue + $expectedResidual);

        $cancel = app(PackageSubscriptionService::class)->cancelRemaining($admin, $partial['subscription']->fresh());

        $this->assertSame(2, $cancel['cancelled_orders']);
        $this->assertSame($prepaid, $cancel['refunded_amount']);
        $this->assertSame(PackageSubscription::STATUS_CANCELLED, $cancel['subscription']->status);
        $this->assertSame($balanceAfterSubscribe + $prepaid, (int) $user->fresh()->balance);

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
