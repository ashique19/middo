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

    private function startPaidCheckout(User $user, MealPackage $package, MenuItem $menu, City $city, Area $area): string
    {
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

        $component->call('startGatewayPayment')->assertRedirect();

        $redirectUrl = $component->effects['redirect'] ?? null;
        $this->assertIsString($redirectUrl);

        $token = null;
        if (preg_match('#/pay/[^/]+/([^/?]+)#', $redirectUrl, $matches)) {
            $token = $matches[1];
        }
        if (! $token && preg_match('#[?&]token=([^&]+)#', $redirectUrl, $matches)) {
            $token = urldecode($matches[1]);
        }
        if (! $token && preg_match('#/([^/?]+)\?#', parse_url($redirectUrl, PHP_URL_PATH) ?? '', $matches)) {
            $maybe = $matches[1];
            if (strlen($maybe) >= 20) {
                $token = $maybe;
            }
        }

        $this->assertNotEmpty($token, 'Expected payment token in redirect URL: '.$redirectUrl);

        $confirmUrl = URL::temporarySignedRoute(
            'corporate.gateway-prepay.confirm',
            now()->addMinutes(45),
            ['token' => $token]
        );
        $this->actingAs($user)
            ->post($confirmUrl)
            ->assertRedirect(route('corporates.packages.confirm', ['token' => $token]));

        $this->assertDatabaseHas('package_checkout_intents', [
            'payment_token' => $token,
            'status' => PackageCheckoutIntent::STATUS_PAID_AWAITING_OTP,
            'user_id' => $user->id,
        ]);

        return $token;
    }

    public function test_gateway_start_persists_checkout_intent_row(): void
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

        $component->call('startGatewayPayment')->assertRedirect();

        $this->assertDatabaseHas('package_checkout_intents', [
            'user_id' => $user->id,
            'meal_package_id' => $package->id,
            'status' => PackageCheckoutIntent::STATUS_AWAITING_PAYMENT,
        ]);

        Carbon::setTestNow();
    }

    public function test_paid_intent_survives_cache_expiry_and_can_complete_with_otp(): void
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

        $token = $this->startPaidCheckout($user, $package, $menu, $city, $area);

        // Simulate browser abandon: gateway cache + draft expire, but DB intent remains.
        Cache::forget('payment_gateway_checkout_'.$token);
        Cache::forget('package_gateway_draft_'.$token);

        $this->assertNull(app(PaymentGateway::class)->find($token));
        $this->assertDatabaseHas('package_checkout_intents', [
            'payment_token' => $token,
            'status' => PackageCheckoutIntent::STATUS_PAID_AWAITING_OTP,
        ]);

        $confirm = Livewire::actingAs($user)
            ->test(PackageGatewayConfirm::class, ['token' => $token])
            ->assertSet('paid', true)
            ->assertSee('Enter the 4-digit OTP');

        $debugOtp = $confirm->get('debugOtp');
        $this->assertNotEmpty($debugOtp);

        $confirm
            ->set('otpInput', $debugOtp)
            ->call('createPackage')
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

    public function test_packages_page_surfaces_paid_awaiting_otp_and_blocks_new_subscribe(): void
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

        $token = $this->startPaidCheckout($user, $package, $menu, $city, $area);

        Livewire::actingAs($user)
            ->test(Packages::class)
            ->assertSet('pendingPaidCheckout.token', $token)
            ->assertSee('Payment received')
            ->assertSee('Enter OTP');

        // Starting another gateway checkout redirects back to the unpaid OTP confirm.
        $workingDays = $this->workingDays('2026-07');
        $component = Livewire::actingAs($user)
            ->test(PackageSubscribeModal::class)
            ->call('open', $package->id)
            ->set('customerName', 'Dev Signature')
            ->set('mobile', '01710123125')
            ->set('addressLine1', 'Mirpur dhaka')
            ->set('city_id', $city->id)
            ->set('area_id', $area->id);

        for ($i = 0; $i < $workingDays; $i++) {
            $component->call('changeMenuDays', $menu->id, 1);
        }

        $component
            ->call('startGatewayPayment')
            ->assertRedirect(route('corporates.packages.confirm', ['token' => $token]));

        Carbon::setTestNow();
    }

    public function test_packages_page_pokes_otp_for_abandoned_paid_checkout(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 10:00:00', OrderCutoff::timezone()));

        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01710123126',
        ]);
        $menu = $this->makeMenuItem();
        $package = $this->makeRatePlan(79);

        $token = $this->startPaidCheckout($user, $package, $menu, $city, $area);

        $intent = PackageCheckoutIntent::query()->where('payment_token', $token)->firstOrFail();
        $intent->update(['otp_last_sent_at' => now()->subMinutes(10)]);

        Livewire::actingAs($user)
            ->test(Packages::class)
            ->assertSet('pendingPaidCheckout.token', $token);

        $intent->refresh();
        $this->assertTrue($intent->otp_last_sent_at->gt(now()->subMinute()));

        Carbon::setTestNow();
    }
}
