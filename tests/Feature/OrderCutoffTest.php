<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderConfirmationOtp;
use App\Support\OrderCutoff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCutoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_order_cannot_be_cancelled_after_delivery_day_deadline(): void
    {
        $role = Role::create(['name' => 'corporate']);
        $user = User::create([
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Acme',
            'mobile' => '01711999111',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 5000,
        ]);

        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 400,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        $today = now(OrderCutoff::timezone())->toDateString();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'amount_paid' => 0,
            'address' => 'House 1, Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Carbon::setTestNow(
            OrderCutoff::forDate($today)->addMinute()
        );

        $this->assertFalse(OrderCutoff::allowsModification($order->fresh()));

        Sanctum::actingAs($user);
        $this->deleteJson("/api/corporate/orders/{$order->id}")
            ->assertUnprocessable();

        Carbon::setTestNow();
    }

    public function test_place_order_rejects_delivery_date_after_cutoff(): void
    {
        $role = Role::create(['name' => 'corporate']);
        $user = User::create([
            'first_name' => 'Nabila',
            'last_name' => 'Rahman',
            'company_name' => 'Acme',
            'mobile' => '01711999112',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 5000,
        ]);

        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 400,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        $cityId = City::create(['name' => 'Dhaka'])->id;
        $areaId = Area::create(['name' => 'Gulshan', 'city_id' => $cityId])->id;

        $today = now(OrderCutoff::timezone())->toDateString();
        Carbon::setTestNow(OrderCutoff::forDate($today)->addMinute());

        Sanctum::actingAs($user);

        $payload = [
            'menu_item_id' => $menu->id,
            'dates' => [['date' => $today, 'quantity' => 1]],
            'receiver_name' => 'Nabila Rahman',
            'mobile' => $user->mobile,
            'address' => 'House 1, Road 5',
            'city_id' => $cityId,
            'area_id' => $areaId,
        ];

        $this->postJson('/api/corporate/orders/send-otp', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dates']);

        OrderConfirmationOtp::generate($user->mobile);

        $this->postJson('/api/corporate/orders', $payload + [
            'otp' => '1234',
            'payment_method' => 'balance',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dates']);

        Carbon::setTestNow();
    }
}
