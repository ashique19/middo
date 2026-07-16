<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Kitchen\IncomingBoxes;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenMiddoBoxesTest extends TestCase
{
    use RefreshDatabase;

    protected Role $kitchenRole;

    protected Role $deliveryRole;

    protected User $kitchen;

    protected User $rider;

    protected User $customer;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kitchenRole = Role::create(['name' => 'kitchen']);
        $this->deliveryRole = Role::create(['name' => 'delivery']);
        Role::create(['name' => 'corporate']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01710000001',
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
        ]);

        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'One',
            'mobile' => '01710000002',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
        ]);

        $corporateRole = Role::where('name', 'corporate')->first();

        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01710000003',
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

    protected function makeBox(array $overrides = []): MiddoBox
    {
        static $seq = 1;

        return MiddoBox::create(array_merge([
            'qr_code_id' => 'MB-'.str_pad((string) $seq++, 6, '0', STR_PAD_LEFT),
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ], $overrides));
    }

    protected function createAssignedOrder(int $quantity = 2): Order
    {
        $today = now('Asia/Dhaka')->toDateString();

        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => $quantity,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 250 * $quantity,
            'address' => 'Test Address',
            'order_status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-DISPATCH-001',
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);

        $group->orders()->attach($order->id);

        return $order->fresh(['orderGroup']);
    }

    public function test_kitchen_can_list_and_receive_incoming_boxes(): void
    {
        $box = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->rider->id,
        ]);

        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'in_transit',
            'log_action' => 'dispatched_to_kitchen',
        ]);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.middo-boxes.incoming'))
            ->assertOk()
            ->assertSee($box->qr_code_id)
            ->assertSee('On the way to kitchen')
            ->assertSee('Received');

        Livewire::actingAs($this->kitchen)
            ->test(IncomingBoxes::class)
            ->call('receiveBox', $box->id)
            ->assertSet('statusMessage', "Received {$box->qr_code_id} into kitchen inventory.");

        $box->refresh();
        $this->assertSame($this->kitchen->id, $box->held_by_user_id);
        $this->assertSame($this->kitchen->id, $box->kitchen_id);
        $this->assertTrue($box->isAtKitchen($this->kitchen->id));
        $this->assertSame('At kitchen', $box->locationLabel());
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'received_at_kitchen',
            'custody_status' => 'assigned_at_kitchen',
        ]);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.middo-boxes.at-kitchen'))
            ->assertOk()
            ->assertSee($box->qr_code_id);
    }

    public function test_kitchen_can_send_box_at_kitchen_to_warehouse(): void
    {
        $box = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
        ]);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.middo-boxes.at-kitchen'))
            ->assertOk()
            ->assertSee($box->qr_code_id)
            ->assertSee('Send to Middo warehouse');

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('sendToWarehouse', $box->id)
            ->assertSet('statusMessage', "{$box->qr_code_id} sent to Middo warehouse.");

        $box->refresh();
        $this->assertNull($box->kitchen_id);
        $this->assertNull($box->held_by_user_id);
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'returned_to_warehouse',
        ]);
    }

    public function test_active_orders_show_box_low_and_dispatch_uses_selected_boxes(): void
    {
        $order = $this->createAssignedOrder(2);

        $box1 = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
        ]);
        $box2 = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
        ]);

        // Only 1 box would be low; with 2 boxes inventory matches qty 2 — add a higher qty check via page
        $this->actingAs($this->kitchen)
            ->get(route('kitchen.orders.active'))
            ->assertOk()
            ->assertSee('Dispatch deadline')
            ->assertSee('Dispatch')
            ->assertDontSee('Box Low');

        // Create another order needing more boxes than available after we only keep inventory at 2
        $lowOrder = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 5,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 1250,
            'address' => 'Test Address',
            'order_status' => 'pending',
            'payment_status' => 'paid',
        ]);
        $order->orderGroup->orders()->attach($lowOrder->id);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.orders.active'))
            ->assertOk()
            ->assertSee('Box Low');

        Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->assertSet('showModal', true)
            ->assertSet('requiredQuantity', 2)
            ->call('toggleBox', $box1->id)
            ->call('toggleBox', $box2->id)
            ->call('dispatchOrder')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'processing',
        ]);

        $this->assertNotNull($order->fresh()->dispatched_at);

        $this->assertDatabaseHas('order_middo_boxes', [
            'order_id' => $order->id,
            'middo_box_id' => $box1->id,
        ]);

        $box1->refresh();
        $this->assertSame($this->kitchen->id, $box1->kitchen_id);
        $this->assertSame($this->kitchen->id, $box1->held_by_user_id);
        $this->assertSame(0, $box1->total_uses_count);
    }

    public function test_operation_assign_requires_kitchen_destination(): void
    {
        $box = $this->makeBox([
            'asset_status' => 'at_middo_warehouse',
            'held_by_user_id' => null,
            'kitchen_id' => null,
        ]);

        $operationRole = Role::create(['name' => 'operation']);
        $operator = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01710000004',
            'password' => 'password',
            'role_id' => $operationRole->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($operator)
            ->test(\App\Livewire\Operation\AssignMiddoBoxesModal::class)
            ->call('openModal', ['boxIds' => [$box->id]])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertSet('showModal', false);

        $box->refresh();
        $this->assertSame($this->kitchen->id, $box->kitchen_id);
        $this->assertSame($this->rider->id, $box->held_by_user_id);
        $this->assertTrue($box->isIncomingToKitchen($this->kitchen->id));
    }
}
