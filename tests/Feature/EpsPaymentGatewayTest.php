<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateWalletTopUp;
use App\Support\Payments\EpsPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EpsPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    #[Test]
    public function create_checkout_initializes_eps_and_returns_redirect_url(): void
    {
        Http::fake([
            'https://sandboxpgapi.eps.com.bd/v1/Auth/GetToken' => Http::response([
                'token' => 'test-eps-token',
                'expireDate' => now()->addHour()->toIso8601String(),
                'errorMessage' => null,
                'errorCode' => null,
            ], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/InitializeEPS' => Http::response([
                'TransactionId' => 'eps-txn-111',
                'RedirectURL' => 'https://pg.eps.com.bd/PG?data=eps-txn-111',
                'ErrorMessage' => '',
                'ErrorCode' => null,
            ], 200),
        ]);

        $user = $this->makeCorporate();

        $checkout = app(PaymentGateway::class)->createCheckout($user->id, 500, [
            'purpose' => CorporateWalletTopUp::PURPOSE,
            'user_id' => $user->id,
        ]);

        $this->assertSame(500, $checkout['amount']);
        $this->assertFalse($checkout['paid']);
        $this->assertSame('https://pg.eps.com.bd/PG?data=eps-txn-111', $checkout['payment_url']);
        $this->assertNotEmpty($checkout['token']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'InitializeEPS')) {
                return false;
            }

            $data = $request->data();

            return ($data['totalAmount'] ?? null) === 500.0
                && ($data['storeId'] ?? null) === 'd44e705f-9e3a-41de-98b1-1674631637da'
                && ($data['ValueB'] ?? null) === CorporateWalletTopUp::PURPOSE
                && isset($data['successUrl'], $data['failUrl'], $data['cancelUrl']);
        });
    }

    #[Test]
    public function success_callback_verifies_marks_paid_and_credits_wallet(): void
    {
        Http::fake([
            'https://sandboxpgapi.eps.com.bd/v1/Auth/GetToken' => Http::response([
                'token' => 'test-eps-token',
            ], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/InitializeEPS' => Http::response([
                'TransactionId' => 'eps-txn-222',
                'RedirectURL' => 'https://pg.eps.com.bd/PG?data=eps-txn-222',
                'ErrorMessage' => '',
            ], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/CheckMerchantTransactionStatus*' => Http::response([
                'MerchantTransactionId' => 'ignored',
                'Status' => 'Success',
                'TotalAmount' => '2500.00',
                'ErrorMessage' => '',
            ], 200),
        ]);

        $user = $this->makeCorporate(['balance' => 100]);
        $checkout = app(PaymentGateway::class)->createCheckout($user->id, 2500, [
            'purpose' => CorporateWalletTopUp::PURPOSE,
            'user_id' => $user->id,
        ]);

        $response = $this->get(route('payments.eps.success', [
            'token' => $checkout['token'],
            'merchantTransactionId' => $checkout['merchant_transaction_id'],
        ]));

        $response->assertRedirect();
        $this->assertTrue(app(PaymentGateway::class)->find($checkout['token'])['paid'] ?? false);
        $this->assertSame(2600, $user->fresh()->balance);
    }

    #[Test]
    public function residual_order_payment_is_applied_after_eps_success(): void
    {
        Http::fake([
            'https://sandboxpgapi.eps.com.bd/v1/Auth/GetToken' => Http::response([
                'token' => 'test-eps-token',
            ], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/InitializeEPS' => Http::response([
                'TransactionId' => 'eps-txn-333',
                'RedirectURL' => 'https://pg.eps.com.bd/PG?data=eps-txn-333',
                'ErrorMessage' => '',
            ], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/CheckMerchantTransactionStatus*' => Http::response([
                'Status' => 'Success',
                'TotalAmount' => '420.00',
            ], 200),
        ]);

        $user = $this->makeCorporate();
        $menu = $this->makeMenuItem();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'address' => 'Gulshan',
            'order_status' => 'delivered',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $checkout = app(PaymentGateway::class)->createCheckout($user->id, 420, [
            'purpose' => EpsPaymentGateway::PURPOSE_ORDER_RESIDUAL,
            'order_id' => $order->id,
            'mobile' => $user->mobile,
            'receiver_name' => trim($user->first_name.' '.$user->last_name),
        ]);

        $this->get(route('payments.eps.success', [
            'token' => $checkout['token'],
            'merchantTransactionId' => $checkout['merchant_transaction_id'],
        ]))->assertRedirect();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame(420, (int) $order->amount_paid);
        $this->assertSame('delivered_and_paid', $order->order_status);
    }

    #[Test]
    public function order_payment_confirm_redirects_to_eps_hosted_checkout(): void
    {
        Http::fake([
            'https://sandboxpgapi.eps.com.bd/v1/Auth/GetToken' => Http::response([
                'token' => 'test-eps-token',
            ], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/InitializeEPS' => Http::response([
                'TransactionId' => 'eps-txn-444',
                'RedirectURL' => 'https://pg.eps.com.bd/PG?data=eps-txn-444',
                'ErrorMessage' => '',
            ], 200),
        ]);

        $user = $this->makeCorporate();
        $menu = $this->makeMenuItem();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 300,
            'amount_paid' => 0,
            'address' => 'Banani',
            'order_status' => 'delivered',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $url = URL::temporarySignedRoute(
            'public.order-payment.confirm',
            now()->addDay(),
            ['order' => $order->id]
        );

        $this->post($url)->assertRedirect('https://pg.eps.com.bd/PG?data=eps-txn-444');
        $this->assertFalse($order->fresh()->isPaid());
    }

    private function makeCorporate(array $overrides = []): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'corporate']);

        return User::create(array_merge([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'company_name' => 'Middo Demo Corp',
            'mobile' => '01710998877',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 0,
            'address' => 'House 12, Road 5',
        ], $overrides));
    }

    private function makeMenuItem(): MenuItem
    {
        return MenuItem::create([
            'name' => 'Beef Tehari Combo',
            'summary' => 'Fragrant tehari for offices',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-2.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);
    }
}
