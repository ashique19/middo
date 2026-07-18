<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderConfirmationOtp;
use App\Support\OrderPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashOnDeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_order_can_be_placed_with_cash_on_delivery(): void
    {
        $role = Role::create(['name' => 'corporate']);
        $user = User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 5000,
        ]);

        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        Sanctum::actingAs($user);

        $payload = [
            'menu_item_id' => $menu->id,
            'delivery_time' => '12:00 PM',
            'receiver_name' => 'Corporate User',
            'mobile' => '01310123452',
            'address' => 'House 12, Road 5',
            'city_id' => $city->id,
            'area_id' => $area->id,
            'dates' => [
                ['date' => now('Asia/Dhaka')->addDay()->format('Y-m-d'), 'quantity' => 1],
            ],
        ];

        $this->postJson('/api/corporate/orders/send-otp', $payload)
            ->assertOk()
            ->assertJsonPath('cod_allowed', true)
            ->assertJsonPath('prepayment.required', false);

        OrderConfirmationOtp::generate('01310123452');

        $this->postJson('/api/corporate/orders', $payload + [
            'otp' => '1234',
            'payment_method' => OrderPaymentMethod::CASH_ON_DELIVERY,
        ])
            ->assertCreated()
            ->assertJsonPath('orders.0.payment_method', OrderPaymentMethod::CASH_ON_DELIVERY)
            ->assertJsonPath('orders.0.payment_method_label', 'Cash on Delivery')
            ->assertJsonPath('orders.0.payment_status', 'pending')
            ->assertJsonPath('orders.0.amount_paid', 0);

        $order = Order::query()->first();
        $this->assertSame(OrderPaymentMethod::CASH_ON_DELIVERY, $order->payment_method);
        $this->assertSame('Cash on Delivery', $order->paymentMethodLabel());
        $this->assertSame(5000, $user->fresh()->balance);
    }

    public function test_cod_not_allowed_when_prepayment_required(): void
    {
        $role = Role::create(['name' => 'corporate']);
        $user = User::create([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123452',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 5000,
        ]);

        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

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
                ['date' => now('Asia/Dhaka')->addDay()->format('Y-m-d'), 'quantity' => 1],
            ],
        ];

        $this->postJson('/api/corporate/orders/send-otp', $payload)
            ->assertOk()
            ->assertJsonPath('cod_allowed', false)
            ->assertJsonPath('prepayment.required', true);

        OrderConfirmationOtp::generate('01710999888');

        $this->postJson('/api/corporate/orders', $payload + [
            'otp' => '1234',
            'payment_method' => OrderPaymentMethod::CASH_ON_DELIVERY,
        ])->assertUnprocessable();
    }
}
