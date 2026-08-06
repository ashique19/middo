<?php

namespace Tests\Feature;

use App\Livewire\Corporate\Dashboard;
use App\Livewire\Corporate\ScheduledOrders;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateOrderPresentation;
use App\Support\OrderPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class CorporateCodMakePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_cod_order_shows_make_payment_on_dashboard(): void
    {
        [$user, $menu] = $this->seedCorporate();
        $this->createCodOrder($user, $menu, total: 420);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Make Payment')
            ->assertSee('৳420')
            ->assertSeeHtml('data-testid="make-payment-button"');
    }

    public function test_unpaid_cod_order_shows_make_payment_on_scheduled_list(): void
    {
        [$user, $menu] = $this->seedCorporate();
        $this->createCodOrder($user, $menu, total: 350);

        Livewire::actingAs($user)
            ->test(ScheduledOrders::class)
            ->assertSee('Make Payment')
            ->assertSeeHtml('data-testid="make-payment-button"');
    }

    public function test_paid_order_does_not_show_make_payment(): void
    {
        [$user, $menu] = $this->seedCorporate();
        Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 420,
            'prepaid_amount' => 420,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => OrderPaymentMethod::GATEWAY,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertDontSeeHtml('data-testid="make-payment-button"');
    }

    public function test_presentation_exposes_signed_payment_url_only_for_unpaid_cod(): void
    {
        [$user, $menu] = $this->seedCorporate();
        $cod = $this->createCodOrder($user, $menu, total: 280);
        $paid = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDays(2)->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 280,
            'amount_paid' => 280,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => OrderPaymentMethod::BALANCE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $codRow = CorporateOrderPresentation::present($cod);
        $this->assertTrue($codRow['can_pay_online']);
        $this->assertSame(280, $codRow['amount_due']);
        $this->assertNotEmpty($codRow['online_payment_url']);

        $paidRow = CorporateOrderPresentation::present($paid);
        $this->assertFalse($paidRow['can_pay_online']);
        $this->assertNull($paidRow['online_payment_url']);
    }

    public function test_confirming_online_payment_marks_cod_order_paid_as_gateway(): void
    {
        [$user, $menu] = $this->seedCorporate();
        $order = $this->createCodOrder($user, $menu, total: 420);

        $confirmUrl = URL::temporarySignedRoute(
            'public.order-payment.confirm',
            now()->addDay(),
            ['order' => $order->id]
        );

        $response = $this->post($confirmUrl);
        $response->assertRedirect();
        $response->assertSessionHas('order_payment_just_completed', true);

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame(420, (int) $order->amount_paid);
        $this->assertSame(OrderPaymentMethod::GATEWAY, $order->payment_method);
        $this->assertSame('paid', $order->payment_status);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Thank you for your payment')
            ->assertSee('Go to Dashboard')
            ->assertSee(route('corporates.dashboard'), false)
            ->assertDontSee('This order is already paid. Thank you!');

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'event' => 'payment_status_changed',
        ]);
    }

    public function test_corporate_online_payment_appears_as_payment_updated_in_track_modal(): void
    {
        [$user, $menu] = $this->seedCorporate();
        $order = $this->createCodOrder($user, $menu, total: 420);

        $confirmUrl = URL::temporarySignedRoute(
            'public.order-payment.confirm',
            now()->addDay(),
            ['order' => $order->id]
        );
        $this->post($confirmUrl)->assertRedirect();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Corporate\TrackOrderModal::class)
            ->dispatch('open-track-order-modal', orderId: $order->id)
            ->assertSet('showModal', true)
            ->assertSee('Payment Updated')
            ->assertSee('Payment changed from Pending to Paid')
            ->assertSee('Amount paid is now ৳420')
            ->assertDontSee('Order details were updated.');
    }

    public function test_already_paid_order_page_keeps_generic_message_without_fresh_payment_flash(): void
    {
        [$user, $menu] = $this->seedCorporate();
        $order = Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 420,
            'amount_paid' => 420,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => OrderPaymentMethod::GATEWAY,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $url = URL::temporarySignedRoute(
            'public.order-payment',
            now()->addDay(),
            ['order' => $order->id]
        );

        $this->get($url)
            ->assertOk()
            ->assertSee('This order is already paid. Thank you!')
            ->assertDontSee('Thank you for your payment');
    }

    /**
     * @return array{0: User, 1: MenuItem}
     */
    private function seedCorporate(): array
    {
        $role = Role::create(['name' => 'corporate']);
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $user = User::create([
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
        $menu = MenuItem::create([
            'name' => 'Tehari',
            'summary' => 'Test',
            'price' => 420,
            'thumbnail' => 'img/menu/menu-1.jpg',
            'is_featured' => true,
            'display_order' => 1,
        ]);

        return [$user, $menu];
    }

    private function createCodOrder(User $user, MenuItem $menu, int $total): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => $total,
            'amount_paid' => 0,
            'prepaid_amount' => 0,
            'address' => 'Gulshan',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => OrderPaymentMethod::CASH_ON_DELIVERY,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
