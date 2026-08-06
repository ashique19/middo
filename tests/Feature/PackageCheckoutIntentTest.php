<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Livewire\Corporate\PackageGatewayConfirm;
use App\Livewire\Corporate\PackageSubscribeModal;
use App\Livewire\Corporate\Packages;
use App\Models\Area;
use App\Models\City;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\PackageCheckoutIntent;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderConfirmationOtp;
use App\Support\OrderCutoff;
use App\Support\PackageBilling;
use App\Support\PackageGatewayCheckout;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class PackageCheckoutIntentTest extends TestCase
{
    use RefreshDatabase;

    private Role $corporateRole;

    protected function setUp(): void
    {
        parent::setUp();

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
            'balance' => 0,
            'address' => 'House 12, Road 5',
        ], $overrides));
    }

    private function makeCityArea(): array
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return [$city, $area];
    }

    private function makeMenuItem(string $name = 'Office Thali'): MenuItem
    {
        return MenuItem::create([
            'name' => $name,
            'summary' => 'Daily lunch',
            'price' => 150,
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

    private function workingDays(string $month = '2026-07', array $omitted = [5, 6]): int
    {
        return PackageBilling::availableDatesInMonth($month, $omitted)->count();
    }

    /**
     * OTP → verify → make payment session → pay → auto-create package.
     */
    private function otpThenPayCreatesPackage(
        User $user,
        MealPackage $package,
        MenuItem $menu,
        City $city,
        Area $area
    ): string {
        $workingDays = $this->workingDays('2026-07');

        $component = Livewire::actingAs($user)
            ->test(PackageSubscribeModal::class)
            ->call('open', $package->id)
            ->set('customerName', 'Dev Signature')
            ->set('mobile', $user->mobile)
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
            ->assertSet('gatewayPaymentUrl', null)
            ->assertSee('Verify code');

        OrderConfirmationOtp::generate($user->mobile);

        $component
            ->set('otpInput', '1234')
            ->call('verifyGatewayOtp')
            ->assertSet('otpVerified', true)
            ->assertNotSet('gatewayPaymentUrl', null)
            ->assertSee('Make payment');

        $token = (string) $component->get('gatewayPaymentToken');
        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('package_checkout_intents', [
            'payment_token' => $token,
            'status' => PackageCheckoutIntent::STATUS_AWAITING_PAYMENT,
            'user_id' => $user->id,
        ]);

        $confirmUrl = URL::temporarySignedRoute(
            'corporate.gateway-prepay.confirm',
            now()->addMinutes(45),
            ['token' => $token]
        );
        $this->actingAs($user)
            ->post($confirmUrl)
            ->assertRedirect();

        $this->assertDatabaseHas('package_checkout_intents', [
            'payment_token' => $token,
            'status' => PackageCheckoutIntent::STATUS_COMPLETED,
            'user_id' => $user->id,
        ]);

        return $token;
    }

    public function test_gateway_verify_otp_persists_checkout_intent_awaiting_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01710123123',
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-07');

        $component = Livewire::actingAs($user)
            ->test(PackageSubscribeModal::class)
            ->call('open', $package->id)
            ->set('customerName', 'Dev Signature')
            ->set('mobile', '01710123123')
            ->set('addressLine1', 'Mirpur dhaka')
            ->set('city_id', $city->id)
            ->set('area_id', $area->id);

        for ($i = 0; $i < $workingDays; $i++) {
            $component->call('changeMenuDays', $menu->id, 1);
        }

        $component->call('startGatewayPayment')->assertSet('isConfirmingOtp', true);
        OrderConfirmationOtp::generate('01710123123');
        $component->set('otpInput', '1234')->call('verifyGatewayOtp');

        $this->assertDatabaseHas('package_checkout_intents', [
            'user_id' => $user->id,
            'meal_package_id' => $package->id,
            'status' => PackageCheckoutIntent::STATUS_AWAITING_PAYMENT,
        ]);

        Carbon::setTestNow();
    }

    public function test_paid_intent_survives_cache_expiry_and_confirm_page_completes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01710123124',
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-07');
        $quote = PackageBilling::quoteFromSelections(
            $package,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            [5, 6],
            '2026-07'
        );
        $charges = app(\App\Support\ChargeService::class)->quotePackage(
            $area->id,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]]
        );
        $amount = (int) $quote['total_amount'] + (int) ($charges['total'] ?? 0);

        $draft = [
            'customer_name' => 'Dev Signature',
            'mobile' => $user->mobile,
            'address_line1' => 'Mirpur dhaka',
            'city_id' => $city->id,
            'area_id' => $area->id,
            'delivery_window' => '12:00 PM',
            'coupon_code' => null,
        ];
        $checkout = PackageGatewayCheckout::start(
            $user,
            $package->id,
            1,
            [5, 6],
            '2026-07',
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            $amount,
            $draft
        );
        $token = $checkout['token'];
        app(PaymentGateway::class)->markPaid($token);
        PackageGatewayCheckout::markIntentPaid($token);

        Cache::forget('payment_gateway_checkout_'.$token);
        Cache::forget('package_gateway_draft_'.$token);
        Cache::forget('package_gateway_done_'.$token);

        $this->assertDatabaseHas('package_checkout_intents', [
            'payment_token' => $token,
            'status' => PackageCheckoutIntent::STATUS_PAID_AWAITING_OTP,
        ]);

        Livewire::actingAs($user)
            ->test(PackageGatewayConfirm::class, ['token' => $token])
            ->assertRedirect();

        $subscription = PackageSubscription::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($subscription);
        $this->assertSame('paid', $subscription->payment_status);

        $this->assertDatabaseHas('package_checkout_intents', [
            'payment_token' => $token,
            'status' => PackageCheckoutIntent::STATUS_COMPLETED,
            'package_subscription_id' => $subscription->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_packages_page_auto_finishes_paid_pending_intent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01710123125',
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);
        $workingDays = $this->workingDays('2026-07');
        $quote = PackageBilling::quoteFromSelections(
            $package,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            [5, 6],
            '2026-07'
        );
        $charges = app(\App\Support\ChargeService::class)->quotePackage(
            $area->id,
            1,
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]]
        );
        $amount = (int) $quote['total_amount'] + (int) ($charges['total'] ?? 0);

        $checkout = PackageGatewayCheckout::start(
            $user,
            $package->id,
            1,
            [5, 6],
            '2026-07',
            [['menu_item_id' => $menu->id, 'day_count' => $workingDays]],
            $amount,
            [
                'customer_name' => 'Dev Signature',
                'mobile' => $user->mobile,
                'address_line1' => 'Mirpur dhaka',
                'city_id' => $city->id,
                'area_id' => $area->id,
                'delivery_window' => '12:00 PM',
                'coupon_code' => null,
            ]
        );
        $token = $checkout['token'];
        app(PaymentGateway::class)->markPaid($token);
        PackageGatewayCheckout::markIntentPaid($token);

        Livewire::actingAs($user)
            ->test(Packages::class)
            ->assertSet('pendingPaidCheckout', null);

        $this->assertDatabaseHas('package_checkout_intents', [
            'payment_token' => $token,
            'status' => PackageCheckoutIntent::STATUS_COMPLETED,
        ]);

        Carbon::setTestNow();
    }

    public function test_otp_then_pay_end_to_end_creates_package(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01710123126',
            'balance' => 0,
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);

        $this->otpThenPayCreatesPackage($user, $package, $menu, $city, $area);

        $this->assertSame(1, PackageSubscription::query()->where('user_id', $user->id)->count());

        Carbon::setTestNow();
    }
}
