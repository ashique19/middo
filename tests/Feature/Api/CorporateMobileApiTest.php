<?php

namespace Tests\Feature\Api;

use App\Jobs\SendOrderStatusPush;
use App\Models\Area;
use App\Models\City;
use App\Models\DeviceToken;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderConfirmationOtp;
use App\Support\PasswordResetOtp;
use App\Support\SignupOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CorporateMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $corporateRole;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->kitchenRole = Role::create(['name' => 'kitchen']);
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
            'balance' => 10000,
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

    public function test_corporate_can_login_and_receive_token(): void
    {
        $this->makeCorporate();

        $response = $this->postJson('/api/corporate/login', [
            'mobile' => '01310123452',
            'password' => '12345678',
            'device_name' => 'test-device',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.mobile', '01310123452')
            ->assertJsonPath('user.role', 'corporate')
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_corporate_can_register_and_receive_token(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        [$city, $area] = $this->makeCityArea();

        $this->postJson('/api/corporate/register', [
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'mobile' => '01710123456',
            'otp' => '1234',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'company_name' => 'Rahman Foods Ltd',
            'address' => 'House 9, Road 11, Banani',
            'city_id' => $city->id,
            'area_id' => $area->id,
        ])->assertUnprocessable();

        $this->postJson('/api/corporate/register/send-otp', [
            'mobile' => '01710123456',
        ])->assertOk()
            ->assertJsonPath('debug_otp', '1234');

        $this->assertNotNull(SignupOtp::cacheKey('01710123456'));

        $this->postJson('/api/corporate/register', [
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'mobile' => '01710123456',
            'otp' => '0000',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'company_name' => 'Rahman Foods Ltd',
            'address' => 'House 9, Road 11, Banani',
            'city_id' => $city->id,
            'area_id' => $area->id,
        ])->assertUnprocessable();

        $this->postJson('/api/corporate/register/send-otp', [
            'mobile' => '01710123456',
        ])->assertOk();

        $this->postJson('/api/corporate/register', [
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'mobile' => '01710123456',
            'otp' => '1234',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'company_name' => 'Rahman Foods Ltd',
            'address' => 'House 9, Road 11, Banani',
            'city_id' => $city->id,
            'area_id' => $area->id,
        ])->assertCreated()
            ->assertJsonPath('user.mobile', '01710123456')
            ->assertJsonPath('user.company_name', 'Rahman Foods Ltd')
            ->assertJsonPath('user.first_name', 'Nabila')
            ->assertJsonPath('user.last_name', 'Rahman')
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'mobile' => '01710123456',
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Rahman Foods Ltd',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'is_mobile_verified' => 1,
        ]);
    }

    public function test_forgot_and_reset_password_flow(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = $this->makeCorporate(['mobile' => '01810123456']);

        $this->postJson('/api/corporate/forgot-password', [
            'mobile' => '01810123456',
        ])->assertOk()
            ->assertJsonPath('debug_otp', '1234');

        $this->assertNotEmpty(PasswordResetOtp::cacheKey('01810123456'));

        $this->postJson('/api/corporate/reset-password', [
            'mobile' => '01810123456',
            'otp' => '0000',
            'password' => 'newpass99',
            'password_confirmation' => 'newpass99',
        ])->assertUnprocessable();

        $this->postJson('/api/corporate/forgot-password', [
            'mobile' => '01810123456',
        ])->assertOk();

        $this->postJson('/api/corporate/reset-password', [
            'mobile' => '01810123456',
            'otp' => '1234',
            'password' => 'newpass99',
            'password_confirmation' => 'newpass99',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass99', $user->fresh()->password));
    }

    public function test_non_corporate_cannot_login_to_mobile_api(): void
    {
        User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'User',
            'mobile' => '01310123453',
            'password' => '12345678',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ]);

        $this->postJson('/api/corporate/login', [
            'mobile' => '01310123453',
            'password' => '12345678',
        ])->assertForbidden();
    }

    public function test_dashboard_requires_auth_and_returns_metrics(): void
    {
        $user = $this->makeCorporate();
        $menu = $this->makeMenuItem();

        Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 5,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 2100,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/corporate/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.active_orders', 1)
            ->assertJsonCount(1, 'upcoming_orders');
    }

    public function test_place_order_requires_otp_and_receiver_details(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = $this->makeCorporate();
        $menu = $this->makeMenuItem();
        [$city, $area] = $this->makeCityArea();
        Sanctum::actingAs($user);

        $d1 = now('Asia/Dhaka')->addDays(1)->format('Y-m-d');
        $d2 = now('Asia/Dhaka')->addDays(2)->format('Y-m-d');
        $payload = [
            'menu_item_id' => $menu->id,
            'delivery_time' => '12:00 PM',
            'receiver_name' => 'Corporate User',
            'mobile' => '01310123452',
            'address' => 'House 12, Road 5',
            'city_id' => $city->id,
            'area_id' => $area->id,
            'dates' => [
                ['date' => $d1, 'quantity' => 1],
                ['date' => $d2, 'quantity' => 1],
            ],
        ];

        $this->postJson('/api/corporate/orders', $payload + ['otp' => '1234'])
            ->assertUnprocessable();

        $this->postJson('/api/corporate/orders/send-otp', $payload)
            ->assertOk()
            ->assertJsonPath('mobile', '01310123452')
            ->assertJsonPath('prepayment.required', false);

        $this->assertNotNull(OrderConfirmationOtp::cacheKey('01310123452'));

        $this->postJson('/api/corporate/orders', $payload + ['otp' => '0000'])
            ->assertUnprocessable();

        $this->postJson('/api/corporate/orders', $payload + ['otp' => '1234'])
            ->assertCreated()
            ->assertJsonCount(2, 'orders');

        $this->assertDatabaseCount('orders', 2);
        $this->assertTrue(
            Order::query()
                ->where('user_id', $user->id)
                ->where('menu_item_id', $menu->id)
                ->where('quantity', 1)
                ->whereDate('delivery_date', $d1)
                ->exists()
        );
        $this->assertSame('House 12, Road 5', $user->fresh()->address);
        $this->assertTrue((bool) $user->fresh()->is_mobile_verified);
    }

    public function test_receiver_mismatch_requires_full_balance_prepayment(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = $this->makeCorporate(['balance' => 5000]);
        $menu = $this->makeMenuItem();
        [$city, $area] = $this->makeCityArea();
        Sanctum::actingAs($user);

        $payload = [
            'menu_item_id' => $menu->id,
            'delivery_time' => '12:00 PM',
            'receiver_name' => 'Different Desk',
            'mobile' => '01710999888',
            'address' => 'House 12, Road 5',
            'city_id' => $city->id,
            'area_id' => $area->id,
            'dates' => [
                ['date' => now('Asia/Dhaka')->addDay()->format('Y-m-d'), 'quantity' => 2],
            ],
        ];

        $this->postJson('/api/corporate/orders/send-otp', $payload)
            ->assertOk()
            ->assertJsonPath('prepayment.required', true)
            ->assertJsonPath('prepayment.ratio', 1)
            ->assertJsonPath('prepayment.amount', 840);

        $this->postJson('/api/corporate/orders', $payload + ['otp' => '1234'])
            ->assertUnprocessable();

        $this->postJson('/api/corporate/orders/send-otp', $payload)->assertOk();

        $this->postJson('/api/corporate/orders', $payload + [
            'otp' => '1234',
            'payment_method' => 'balance',
        ])->assertCreated()
            ->assertJsonPath('orders.0.payment_status', 'paid')
            ->assertJsonPath('orders.0.amount_paid', 840);

        $this->assertSame(4160, $user->fresh()->balance);
        $this->assertSame('Corporate', $user->fresh()->first_name);
        $this->assertSame('01310123452', $user->fresh()->mobile);
    }

    public function test_meals_above_cod_ceiling_require_full_prepayment(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = $this->makeCorporate(['balance' => 10000]);
        $menu = $this->makeMenuItem();
        [$city, $area] = $this->makeCityArea();
        Sanctum::actingAs($user);

        // Existing active meal qty 1.
        Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $payload = [
            'menu_item_id' => $menu->id,
            'delivery_time' => '12:00 PM',
            'receiver_name' => 'Corporate User',
            'mobile' => '01310123452',
            'address' => 'House 12, Road 5',
            'city_id' => $city->id,
            'area_id' => $area->id,
            // Single day, qty 3 → projected meals = 1 + 3 = 4 (above COD ceiling of 3).
            'dates' => [
                ['date' => now('Asia/Dhaka')->addDays(2)->format('Y-m-d'), 'quantity' => 3],
            ],
        ];

        $this->postJson('/api/corporate/orders/send-otp', $payload)
            ->assertOk()
            ->assertJsonPath('prepayment.required', true)
            ->assertJsonPath('prepayment.ratio', 1)
            ->assertJsonPath('prepayment.amount', 1260)
            ->assertJsonPath('prepayment.projected_active', 4)
            ->assertJsonPath('prepayment.full_prepay_from', 3);

        $this->postJson('/api/corporate/orders', $payload + [
            'otp' => '1234',
            'payment_method' => 'balance',
        ])->assertCreated()
            ->assertJsonPath('orders.0.payment_status', 'paid')
            ->assertJsonPath('orders.0.amount_paid', 1260);

        $this->assertSame(8740, $user->fresh()->balance);
    }

    public function test_gateway_prepay_token_can_complete_mismatch_order(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $user = $this->makeCorporate(['balance' => 100]);
        $menu = $this->makeMenuItem();
        [$city, $area] = $this->makeCityArea();
        Sanctum::actingAs($user);

        $payload = [
            'menu_item_id' => $menu->id,
            'delivery_time' => '12:00 PM',
            'receiver_name' => 'Guest Receiver',
            'mobile' => '01710111222',
            'address' => 'House 12, Road 5',
            'city_id' => $city->id,
            'area_id' => $area->id,
            'dates' => [
                ['date' => now('Asia/Dhaka')->addDay()->format('Y-m-d'), 'quantity' => 1],
            ],
        ];

        $gateway = $this->postJson('/api/corporate/orders/gateway-prepay', $payload)
            ->assertOk()
            ->assertJsonStructure(['payment_token', 'payment_url', 'amount']);

        $token = $gateway->json('payment_token');
        \App\Support\CorporateGatewayPrepay::markPaid($token);

        $this->postJson('/api/corporate/orders/send-otp', $payload)->assertOk();

        $this->postJson('/api/corporate/orders', $payload + [
            'otp' => '1234',
            'payment_method' => 'gateway',
            'payment_token' => $token,
        ])->assertCreated()
            ->assertJsonPath('orders.0.payment_status', 'paid');

        $this->assertSame(100, $user->fresh()->balance);
    }

    /**
     * @return array{0: City, 1: Area}
     */
    private function makeCityArea(): array
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create([
            'name' => 'Gulshan 1',
            'city_id' => $city->id,
        ]);

        return [$city, $area];
    }

    public function test_pending_order_can_be_cancelled_and_edit_is_disabled(): void
    {
        $user = $this->makeCorporate(['balance' => 1000]);
        $menu = $this->makeMenuItem();
        Sanctum::actingAs($user);

        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 840,
            'amount_paid' => 420,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->patchJson("/api/corporate/orders/{$order->id}", ['quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);

        $this->assertSame(2, $order->fresh()->quantity);

        $this->deleteJson("/api/corporate/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('refunded_amount', 420)
            ->assertJsonPath('balance', 1420);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => 'cancelled']);
        $this->assertSame(1420, $user->fresh()->balance);
    }

    public function test_order_edit_endpoint_is_disabled(): void
    {
        $user = $this->makeCorporate();
        $menu = $this->makeMenuItem();
        Sanctum::actingAs($user);

        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->patchJson("/api/corporate/orders/{$order->id}", ['quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);
    }

    public function test_wallet_top_up_increases_balance(): void
    {
        $user = $this->makeCorporate(['balance' => 1000]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/corporate/wallet/top-up', ['amount' => 5000])
            ->assertOk()
            ->assertJsonPath('amount', 5000)
            ->assertJsonStructure(['token', 'payment_url', 'user']);

        // Balance is unchanged until the pseudo gateway is confirmed.
        $this->assertSame(1000, $user->fresh()->balance);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        \App\Support\CorporateGatewayPrepay::markPaid($token);
        $credited = \App\Support\CorporateWalletTopUp::creditIfPaid($token);

        $this->assertTrue($credited['ok']);
        $this->assertSame(6000, $user->fresh()->balance);
    }

    public function test_corporate_can_list_boxes_in_custody(): void
    {
        $user = $this->makeCorporate();
        Sanctum::actingAs($user);

        \App\Models\MiddoBox::create([
            'qr_code_id' => 'MB-000111',
            'box_model_type' => 'standard_insulated',
            'held_by_user_id' => $user->id,
            'asset_status' => 'active',
            'total_uses_count' => 1,
        ]);

        $this->getJson('/api/corporate/boxes')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('boxes.0.qr_code_id', 'MB-000111');
    }

    public function test_profile_can_be_updated_and_password_changed(): void
    {
        [$city, $area] = $this->makeCityArea();
        $user = $this->makeCorporate([
            'city_id' => $city->id,
            'area_id' => $area->id,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/corporate/profile', [
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'mobile' => '01310123452',
            'email' => 'nabila@example.com',
            'address' => 'House 22, Road 7',
            'city_id' => $city->id,
            'area_id' => $area->id,
        ])->assertOk()
            ->assertJsonPath('user.first_name', 'Nabila')
            ->assertJsonPath('user.address', 'House 22, Road 7');

        $this->assertSame('Nabila', $user->fresh()->first_name);

        $this->postJson('/api/corporate/change-password', [
            'current_password' => 'wrong-pass',
            'password' => 'newpass99',
            'password_confirmation' => 'newpass99',
        ])->assertUnprocessable();

        $this->postJson('/api/corporate/change-password', [
            'current_password' => '12345678',
            'password' => 'newpass99',
            'password_confirmation' => 'newpass99',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass99', $user->fresh()->password));
    }

    public function test_support_message_can_be_submitted_once(): void
    {
        $user = $this->makeCorporate();
        $menu = $this->makeMenuItem();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 840,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/corporate/orders/{$order->id}/support", [
            'category' => 'delivery',
            'message' => 'Two boxes arrived without raita cups for Banani.',
        ])->assertCreated();

        $this->postJson("/api/corporate/orders/{$order->id}/support", [
            'message' => 'Following up — still waiting on a response.',
        ])->assertCreated();

        $this->assertSame(2, OrderComplaint::query()->where('order_id', $order->id)->count());

        $root = OrderComplaint::threadForOrder($order->id);
        $root->markResolved($user->id);

        $this->postJson("/api/corporate/orders/{$order->id}/support", [
            'message' => 'Should fail after complete.',
        ])->assertStatus(422);
    }

    public function test_device_token_can_be_registered_and_unregistered(): void
    {
        $user = $this->makeCorporate();
        Sanctum::actingAs($user);

        $this->postJson('/api/corporate/device-tokens', [
            'token' => 'fcm-test-token-abcdefghijklmnopqrstuvwxyz',
            'platform' => 'android',
            'device_name' => 'Pixel Test',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-test-token-abcdefghijklmnopqrstuvwxyz',
            'platform' => 'android',
        ]);

        $this->deleteJson('/api/corporate/device-tokens', [
            'token' => 'fcm-test-token-abcdefghijklmnopqrstuvwxyz',
        ])->assertOk();

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm-test-token-abcdefghijklmnopqrstuvwxyz',
        ]);
    }

    public function test_order_status_change_dispatches_push_notification(): void
    {
        Queue::fake();

        $user = $this->makeCorporate();
        $menu = $this->makeMenuItem();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $order->update(['order_status' => 'processing']);

        Queue::assertPushed(SendOrderStatusPush::class, function (SendOrderStatusPush $job) use ($order) {
            return $job->orderId === $order->id && $job->status === 'processing';
        });
    }
}
