<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $rider;

    protected User $customer;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01810000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'One',
            'mobile' => '01810000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01810000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'Lunch Box A',
            'summary' => 'Daily lunch',
            'price' => 250,
            'kitchen_commission' => 50,
        ]);
    }

    protected function makeBoxAtKitchen(string $qr): MiddoBox
    {
        return MiddoBox::create([
            'qr_code_id' => $qr,
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
            'total_uses_count' => 0,
        ]);
    }

    protected function createKitchenDispatchedOrder(int $quantity = 2): Order
    {
        $today = now('Asia/Dhaka')->toDateString();

        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => $quantity,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 250 * $quantity,
            'address' => 'Corporate HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-DELIVERY-001',
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $boxes = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $boxes[] = $this->makeBoxAtKitchen('MB-D'.str_pad((string) $i, 5, '0', STR_PAD_LEFT))->id;
        }

        Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->call('toggleBox', $boxes[0])
            ->call('toggleBox', $boxes[1] ?? $boxes[0])
            ->call('dispatchOrder');

        return $order->fresh(['middoBoxes', 'orderGroup']);
    }

    public function test_delivery_dashboard_shows_tiles(): void
    {
        $pendingBox = MiddoBox::create([
            'qr_code_id' => 'MB-PENDING1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->rider->id,
            'total_uses_count' => 0,
        ]);

        $this->createKitchenDispatchedOrder(2);

        $this->actingAs($this->rider)
            ->get(route('delivery.dashboard'))
            ->assertOk()
            ->assertSee('Kitchen dispatches')
            ->assertSee('Middo boxes pending run')
            ->assertSee('(1)'); // kitchen dispatches count and/or pending boxes

        $this->assertNotNull($pendingBox->id);
    }

    public function test_rider_can_accept_kitchen_dispatch_and_deliver_to_consumer(): void
    {
        $order = $this->createKitchenDispatchedOrder(2);

        $this->actingAs($this->rider)
            ->get(route('delivery.kitchen-dispatches'))
            ->assertOk()
            ->assertSee('#'.$order->id)
            ->assertSee('Pick up packed order');

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->assertSet('statusMessage', 'Accepted order #'.$order->id.'. Status is now On the way to delivery.');

        $order->refresh();
        $this->assertSame($this->rider->id, $order->delivery_rider_id);
        $this->assertSame('on_the_way_to_delivery', $order->order_status);

        foreach ($order->middoBoxes as $box) {
            $this->assertSame($this->rider->id, $box->fresh()->held_by_user_id);
            $this->assertDatabaseHas('middo_box_logs', [
                'order_id' => $order->id,
                'middo_box_id' => $box->id,
                'log_action' => 'picked_by_delivery_from_kitchen',
            ]);
        }

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id)
            ->assertSet('statusMessage', 'Delivered order #'.$order->id.'. Boxes are now with the customer.');

        $order->refresh();
        $this->assertSame('delivered', $order->order_status);

        foreach ($order->middoBoxes as $box) {
            $this->assertSame($this->customer->id, $box->fresh()->held_by_user_id);
            $this->assertDatabaseHas('middo_box_logs', [
                'order_id' => $order->id,
                'middo_box_id' => $box->id,
                'log_action' => 'delivered_to_corporate',
                'custody_status' => 'with_customer',
            ]);
        }
    }

    public function test_another_rider_cannot_accept_already_claimed_dispatch(): void
    {
        $order = $this->createKitchenDispatchedOrder(2);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id);

        $otherRider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Two',
            'mobile' => '01810000004',
            'password' => 'password',
            'role_id' => Role::where('name', 'delivery')->value('id'),
            'status' => 'active',
        ]);

        Livewire::actingAs($otherRider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->assertSet('errorMessage', 'This kitchen dispatch is no longer available to accept.');
    }

    public function test_rider_cash_payment_and_receive_boxes(): void
    {
        $order = $this->createKitchenDispatchedOrder(2);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->call('deliverToConsumer', $order->id);

        $this->assertSame(0, $this->rider->fresh()->balance);

        $this->actingAs($this->rider)
            ->get(route('delivery.orders.delivered'))
            ->assertOk()
            ->assertSee('Payment')
            ->assertSee('Receive Boxes');

        Livewire::actingAs($this->rider)
            ->test(\App\Livewire\Delivery\PaymentModal::class)
            ->call('openModal', $order->id)
            ->assertSet('showModal', true)
            ->assertSet('totalAmount', 500)
            ->call('selectCash')
            ->call('confirmCashPayment')
            ->assertSet('showModal', false);

        $order->refresh();
        $this->assertSame('delivered_and_paid', $order->order_status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(500, $this->rider->fresh()->balance);

        Livewire::actingAs($this->rider)
            ->test(\App\Livewire\Delivery\DeliveredOrders::class)
            ->call('receiveBoxes', $order->id)
            ->assertSet('statusMessage', 'Received boxes for order #'.$order->id.'.');

        foreach ($order->middoBoxes as $box) {
            $this->assertSame($this->rider->id, $box->fresh()->held_by_user_id);
            $this->assertDatabaseHas('middo_box_logs', [
                'order_id' => $order->id,
                'middo_box_id' => $box->id,
                'log_action' => 'picked_from_corporate_by_delivery',
                'custody_status' => 'collected_by_rider',
            ]);
        }
    }

    public function test_online_payment_sends_sms_link(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            config('services.mimsms.base_url') => \Illuminate\Support\Facades\Http::response(['status' => 'OK'], 200),
        ]);

        $order = $this->createKitchenDispatchedOrder(2);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->call('deliverToConsumer', $order->id);

        Livewire::actingAs($this->rider)
            ->test(\App\Livewire\Delivery\PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectOnline')
            ->assertSet('receiverPhone', $this->customer->mobile)
            ->set('receiverPhone', '01719998888')
            ->call('sendOnlinePaymentLink')
            ->assertSet('successMessage', 'Payment link sent to 01719998888.');

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), 'mimsms')
                && ($request['mobileNumber'] ?? null) === '8801719998888';
        });
    }
}
