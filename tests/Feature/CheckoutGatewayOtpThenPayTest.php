<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Livewire\Public\OrderCheckoutModal;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateOrderGatewayCheckout;
use App\Support\OrderConfirmationOtp;
use App\Support\OrderPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutGatewayOtpThenPayTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_payment_places_order_automatically_after_pay(): void
    {
        $user = $this->makeCorporate();
        $menu = $this->makeMenu();
        [$city, $area] = $this->cityArea();

        $component = Livewire::actingAs($user)
            ->test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id)
            ->set('customerName', 'Corporate User')
            ->set('mobile', '01310123452')
            ->set('addressLine1', 'House 12, Road 5')
            ->set('city_id', $city->id)
            ->set('area_id', $area->id)
            ->set('paymentMethod', OrderPaymentMethod::GATEWAY)
            ->call('initiateOrderConfirmation')
            ->assertSet('isConfirmingOtp', true)
            ->assertSet('otpVerified', false)
            ->assertSet('gatewayPaymentUrl', null)
            ->assertDontSee('Make payment')
            ->assertSee('Verify code');

        OrderConfirmationOtp::generate('01310123452');

        $component
            ->set('otpInput', '1234')
            ->call('verifyGatewayOtp')
            ->assertSet('otpVerified', true)
            ->assertNotSet('gatewayPaymentUrl', null)
            ->assertSee('Make payment')
            ->assertSeeHtml('data-testid="checkout-waiting-payment"')
            ->assertDontSeeHtml('data-testid="checkout-place-after-payment"')
            ->assertDontSee('Verify code');

        $token = $component->get('gatewayPaymentToken');
        $this->assertNotEmpty($token);

        // Still unpaid → poll does nothing.
        $component->call('checkGatewayPaymentCompletion');
        $this->assertSame(0, Order::query()->where('user_id', $user->id)->count());

        app(PaymentGateway::class)->markPaid($token);

        $completed = CorporateOrderGatewayCheckout::completeIfPaid($token);
        $this->assertTrue($completed['ok'] ?? false);
        $this->assertNotEmpty($completed['order_ids'] ?? []);

        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
        $order = Order::query()->where('user_id', $user->id)->first();
        $this->assertSame(OrderPaymentMethod::GATEWAY, $order->payment_method);
        $this->assertSame('paid', $order->payment_status);

        // Idempotent if modal poll runs afterward.
        $again = CorporateOrderGatewayCheckout::completeIfPaid($token);
        $this->assertTrue($again['ok'] ?? false);
        $this->assertTrue($again['already_done'] ?? false);
        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
    }

    public function test_pseudo_confirm_redirects_to_dashboard_after_placing_order(): void
    {
        $user = $this->makeCorporate();
        $menu = $this->makeMenu();
        [$city, $area] = $this->cityArea();

        $component = Livewire::actingAs($user)
            ->test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id)
            ->set('customerName', 'Corporate User')
            ->set('mobile', '01310123452')
            ->set('addressLine1', 'House 12, Road 5')
            ->set('city_id', $city->id)
            ->set('area_id', $area->id)
            ->set('paymentMethod', OrderPaymentMethod::GATEWAY)
            ->call('initiateOrderConfirmation');

        OrderConfirmationOtp::generate('01310123452');
        $component->set('otpInput', '1234')->call('verifyGatewayOtp');
        $url = $component->get('gatewayPaymentUrl');
        $this->assertNotEmpty($url);

        // Convert signed GET payment URL into the POST confirm endpoint.
        $confirmUrl = str_replace('/pay/corporate-prepay/', '/pay/corporate-prepay/', $url);
        // paymentUrl is temporarySignedRoute for show; confirm uses POST to same path with different name.
        $token = $component->get('gatewayPaymentToken');
        $signedConfirm = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'corporate.gateway-prepay.confirm',
            now()->addMinutes(45),
            ['token' => $token]
        );

        $this->actingAs($user)
            ->post($signedConfirm)
            ->assertRedirect(route('corporates.dashboard'));

        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
    }

    private function makeCorporate(): User
    {
        $role = Role::create(['name' => 'corporate']);

        return User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 0,
            'city_id' => null,
            'area_id' => null,
            'address' => 'House 12, Road 5',
        ]);
    }

    private function makeMenu(): MenuItem
    {
        return MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);
    }

    /**
     * @return array{0: City, 1: Area}
     */
    private function cityArea(): array
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return [$city, $area];
    }
}
