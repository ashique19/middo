<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateOrderGatewayCheckout;
use App\Support\OrderPaymentMethod;
use App\Support\Payments\EpsPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CorporatePaymentFailDashboardLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_prepay_failed_screen_links_to_dashboard(): void
    {
        $user = $this->seedCorporate();
        $checkout = app(PaymentGateway::class)->createCheckout($user->id, 500, [
            'purpose' => CorporateOrderGatewayCheckout::PURPOSE,
            'user_id' => $user->id,
        ]);

        $url = URL::temporarySignedRoute(
            'corporate.gateway-prepay.show',
            now()->addMinutes(45),
            [
                'token' => $checkout['token'],
                'eps_status' => 'unpaid',
                'eps_message' => 'Payment was not completed.',
            ]
        );

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertSee('Payment was not completed.')
            ->assertSee('Go to Dashboard')
            ->assertSeeHtml('data-testid="gateway-prepay-dashboard-link"')
            ->assertSee(route('corporates.dashboard'), false);
    }

    public function test_order_payment_page_shows_dashboard_link_after_failed_or_cancelled_flash(): void
    {
        $user = $this->seedCorporate();
        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => OrderPaymentMethod::CASH_ON_DELIVERY,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $url = URL::temporarySignedRoute(
            'public.order-payment',
            now()->addDay(),
            ['order' => $order->id]
        );

        $this->withSession([
            'payment_error' => 'Payment was not completed.',
            'order_payment_failed_or_cancelled' => true,
        ])
            ->get($url)
            ->assertOk()
            ->assertSee('Payment was not completed.')
            ->assertSee('Go to Dashboard')
            ->assertSeeHtml('data-testid="order-payment-dashboard-link"')
            ->assertSee(route('corporates.dashboard'), false);
    }

    public function test_eps_fail_callback_for_residual_order_flashes_dashboard_link_state(): void
    {
        config([
            'payments.driver' => 'eps',
            'payments.eps.sandbox' => true,
            'payments.eps.merchant_id' => '29e86e70-0ac6-45eb-ba04-9fcb0aaed12a',
            'payments.eps.store_id' => 'd44e705f-9e3a-41de-98b1-1674631637da',
            'payments.eps.username' => 'Epsdemo@gmail.com',
            'payments.eps.password' => 'Epsdemo258@',
            'payments.eps.hash_key' => 'FHZxyzeps56789gfhg678ygu876o=',
        ]);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->app->singleton(PaymentGateway::class, fn () => new EpsPaymentGateway);

        Http::fake([
            'https://sandboxpgapi.eps.com.bd/v1/Auth/GetToken' => Http::response([
                'token' => 'test-eps-token',
            ], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/InitializeEPS' => Http::response([
                'TransactionId' => 'eps-txn-fail-1',
                'RedirectURL' => 'https://pg.eps.com.bd/PG?data=eps-txn-fail-1',
                'ErrorMessage' => '',
            ], 200),
        ]);

        $user = $this->seedCorporate();
        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => OrderPaymentMethod::CASH_ON_DELIVERY,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $checkout = app(PaymentGateway::class)->createCheckout($user->id, 420, [
            'purpose' => EpsPaymentGateway::PURPOSE_ORDER_RESIDUAL,
            'order_id' => $order->id,
            'mobile' => $user->mobile,
        ]);

        $response = $this->get(route('payments.eps.fail', [
            'token' => $checkout['token'],
            'merchantTransactionId' => $checkout['merchant_transaction_id'],
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('order_payment_failed_or_cancelled', true);
        $response->assertSessionHas('payment_error');

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Go to Dashboard')
            ->assertSeeHtml('data-testid="order-payment-dashboard-link"');
    }

    private function seedCorporate(): User
    {
        $role = Role::create(['name' => 'corporate']);
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 1000,
            'city_id' => $city->id,
            'area_id' => $area->id,
            'address' => 'House 12, Road 5',
        ]);
    }
}
