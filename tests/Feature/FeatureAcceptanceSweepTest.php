<?php

namespace Tests\Feature;

use App\Livewire\Corporate\ScheduledOrders;
use App\Livewire\Kitchen\ActiveOrders as KitchenActiveOrders;
use App\Livewire\Operation\ActiveOrders as OperationActiveOrders;
use App\Livewire\Public\OrderCheckoutModal;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderConfirmationOtp;
use App\Support\OrderCutoff;
use App\Support\OrderPaymentMethod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-end acceptance checks for the merged orders/login features.
 */
class FeatureAcceptanceSweepTest extends TestCase
{
    use RefreshDatabase;

    private function corporateUser(array $overrides = []): User
    {
        $role = Role::firstOrCreate(['name' => 'corporate']);

        return User::create(array_merge([
            'first_name' => 'Corporate',
            'last_name' => 'User',
            'company_name' => 'Acme',
            'mobile' => '01310123451',
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 10000,
            'address' => 'House 1',
        ], $overrides));
    }

    private function cityArea(): array
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        return [$city, $area];
    }

    private function menu(): MenuItem
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

    public function test_login_page_has_remember_me_and_attempt_persists_token(): void
    {
        $user = $this->corporateUser();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Remember me', false)
            ->assertSee('name="remember"', false);

        $this->post('/login', [
            'mobile' => $user->mobile,
            'password' => '12345678',
            'remember' => '1',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_checkout_minus_at_one_deselects_date(): void
    {
        [$city, $area] = $this->cityArea();
        $user = $this->corporateUser([
            'city_id' => $city->id,
            'area_id' => $area->id,
        ]);
        $menu = $this->menu();

        $this->actingAs($user);

        $component = Livewire::test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id);

        $dates = $component->get('availableDates');
        $this->assertNotEmpty($dates);
        $date = $dates[0];

        $this->assertSame(1, $component->get('quantities')[$date]);

        $component->call('changeDateQuantity', $date, -1)
            ->assertSet("quantities.$date", 0);
    }

    public function test_single_order_cod_is_stored_and_visible_in_presenter(): void
    {
        [$city, $area] = $this->cityArea();
        $user = $this->corporateUser([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123452',
        ]);
        $menu = $this->menu();
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
            ->assertJsonPath('cod_allowed', true);

        OrderConfirmationOtp::generate('01310123452');

        $response = $this->postJson('/api/corporate/orders', $payload + [
            'otp' => '1234',
            'payment_method' => 'cash_on_delivery',
        ])->assertCreated();

        $response->assertJsonPath('orders.0.payment_method', 'cash_on_delivery')
            ->assertJsonPath('orders.0.payment_method_label', 'Cash on Delivery')
            ->assertJsonPath('orders.0.can_delete', true);

        $order = Order::query()->first();
        $this->assertSame('Cash on Delivery', $order->paymentMethodLabel());
        $this->assertSame(OrderPaymentMethod::CASH_ON_DELIVERY, $order->payment_method);
    }

    public function test_edit_api_disabled_and_cancel_blocked_after_cutoff(): void
    {
        $user = $this->corporateUser(['mobile' => '01310123453']);
        $menu = $this->menu();
        Sanctum::actingAs($user);

        $today = now(OrderCutoff::timezone())->toDateString();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 0,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->patchJson("/api/corporate/orders/{$order->id}", ['quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);

        Carbon::setTestNow(OrderCutoff::forDate($today)->addMinute());

        $this->assertFalse(OrderCutoff::allowsModification($order->fresh()));
        $this->deleteJson("/api/corporate/orders/{$order->id}")
            ->assertUnprocessable();

        Carbon::setTestNow();
    }

    public function test_cancel_allowed_before_cutoff(): void
    {
        $user = $this->corporateUser(['mobile' => '01310123454']);
        $menu = $this->menu();
        Sanctum::actingAs($user);

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
            'payment_method' => 'cash_on_delivery',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->deleteJson("/api/corporate/orders/{$order->id}")
            ->assertOk();

        $this->assertSame('cancelled', $order->fresh()->order_status);
    }

    public function test_list_view_toggle_and_excel_export_for_ops_and_kitchen(): void
    {
        $opsRole = Role::firstOrCreate(['name' => 'operation']);
        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen']);

        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01310123460',
            'password' => '12345678',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);

        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'User',
            'mobile' => '01310123461',
            'password' => '12345678',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $menu = $this->menu();
        Order::create([
            'user_id' => $ops->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $ops->id,
            'updated_by' => $ops->id,
        ]);

        $this->actingAs($ops);
        Livewire::test(OperationActiveOrders::class)
            ->assertSet('viewMode', 'default')
            ->call('setViewMode', 'list')
            ->assertSet('viewMode', 'list')
            ->call('exportExcel')
            ->assertFileDownloaded();

        $this->actingAs($kitchen);
        Livewire::test(KitchenActiveOrders::class)
            ->call('setViewMode', 'list')
            ->assertSet('viewMode', 'list')
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_corporate_scheduled_list_view_and_no_edit_affordance(): void
    {
        [$city, $area] = $this->cityArea();
        $user = $this->corporateUser([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123455',
        ]);
        $menu = $this->menu();

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
            'payment_method' => 'cash_on_delivery',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(ScheduledOrders::class)
            ->assertSet('viewMode', 'default')
            ->call('setViewMode', 'list')
            ->assertSet('viewMode', 'list')
            ->assertSee('Cash on Delivery')
            ->assertDontSee('open-edit-order-modal')
            ->assertDontSeeHtml('>View</a>')
            ->assertDontSee('Action');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Remember me');
    }

    public function test_web_checkout_places_cod_single_order(): void
    {
        [$city, $area] = $this->cityArea();
        $user = $this->corporateUser([
            'city_id' => $city->id,
            'area_id' => $area->id,
            'mobile' => '01310123456',
        ]);
        $menu = $this->menu();
        $this->actingAs($user);

        $component = Livewire::test(OrderCheckoutModal::class)
            ->call('loadOrderCheckout', $menu->id);

        $dates = $component->get('availableDates');
        $date = $dates[0];

        // Keep only one date selected.
        foreach ($dates as $d) {
            if ($d !== $date && ($component->get('quantities')[$d] ?? 0) > 0) {
                $component->call('toggleDateSelection', $d);
            }
        }
        if (($component->get('quantities')[$date] ?? 0) === 0) {
            $component->call('toggleDateSelection', $date);
        }

        $component
            ->set('customerName', 'Corporate User')
            ->set('mobile', '01310123456')
            ->set('addressLine1', 'House 12, Road 5')
            ->set('city_id', $city->id)
            ->set('area_id', $area->id)
            ->set('paymentMethod', 'cash_on_delivery')
            ->call('initiateOrderConfirmation')
            ->assertSet('isConfirmingOtp', true)
            ->assertSet('codAllowed', true);

        OrderConfirmationOtp::generate('01310123456');

        $component
            ->set('otpInput', '1234')
            ->call('finalizeOrder');

        $order = Order::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('cash_on_delivery', $order->payment_method);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(0, (int) $order->amount_paid);
    }
}
