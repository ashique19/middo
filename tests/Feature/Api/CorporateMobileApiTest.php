<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderConfirmationOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            'receiver_name' => 'Corporate Desk',
            'mobile' => '01310123452',
            'address' => 'House 12, Road 5',
            'city_id' => $city->id,
            'area_id' => $area->id,
            'dates' => [
                ['date' => $d1, 'quantity' => 2],
                ['date' => $d2, 'quantity' => 3],
            ],
        ];

        $this->postJson('/api/corporate/orders', $payload + ['otp' => '1234'])
            ->assertUnprocessable();

        $this->postJson('/api/corporate/orders/send-otp', $payload)
            ->assertOk()
            ->assertJsonPath('mobile', '01310123452');

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
                ->where('quantity', 2)
                ->whereDate('delivery_date', $d1)
                ->exists()
        );
        $this->assertSame('House 12, Road 5', $user->fresh()->address);
        $this->assertTrue((bool) $user->fresh()->is_mobile_verified);
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

    public function test_wallet_top_up_increases_balance(): void
    {
        $user = $this->makeCorporate(['balance' => 1000]);
        Sanctum::actingAs($user);

        $this->postJson('/api/corporate/wallet/top-up', ['amount' => 5000])
            ->assertOk()
            ->assertJsonPath('user.balance', 6000);

        $this->assertSame(6000, $user->fresh()->balance);
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
            'category' => 'delivery',
            'message' => 'Another complaint should be rejected by the API.',
        ])->assertStatus(422);
    }
}
