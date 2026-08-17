<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\ActiveOrders;
use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Kitchen\IncomingBoxes;
use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Models\Area;
use App\Models\City;
use App\Models\KitchenBoxRequest;
use App\Models\KitchenWarehouseHandoff;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\MiddoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Support\LunchRunFlow;
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
            'order_status' => 'processing',
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
            ->assertSee('Ready to receive', false)
            ->assertSee($this->rider->name, false)
            ->assertSee($this->rider->mobile, false)
            ->assertSee('Confirm receive');

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
            'notes' => 'Received at '.$this->kitchen->name.' ('.$this->kitchen->mobile.')',
        ]);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.middo-boxes.at-kitchen'))
            ->assertOk()
            ->assertSee($box->qr_code_id);
    }

    public function test_kitchen_can_group_receive_incoming_boxes_from_rider(): void
    {
        $boxA = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->rider->id,
        ]);
        $boxB = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->rider->id,
        ]);

        MiddoBoxLog::create([
            'middo_box_id' => $boxA->id,
            'custody_status' => 'in_transit',
            'log_action' => 'handed_to_kitchen_stock',
        ]);
        MiddoBoxLog::create([
            'middo_box_id' => $boxB->id,
            'custody_status' => 'in_transit',
            'log_action' => 'handed_to_kitchen_stock',
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(IncomingBoxes::class)
            ->assertSee('Confirm receive (2)', false)
            ->assertSeeHtml('Confirm receive 2 boxes from Rider One, 01710000002?')
            ->call('receiveAllBoxes', [$boxA->id, $boxB->id])
            ->assertSet('errorMessage', null)
            ->assertSet('statusMessage', 'Received 2 boxes into kitchen inventory.');

        $this->assertTrue($boxA->fresh()->isAtKitchen($this->kitchen->id));
        $this->assertTrue($boxB->fresh()->isAtKitchen($this->kitchen->id));
    }

    public function test_kitchen_can_send_box_at_kitchen_to_warehouse(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '1');

        $box = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
        ]);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.middo-boxes.at-kitchen'))
            ->assertOk()
            ->assertSee($box->qr_code_id)
            ->assertSee('Send to Middo warehouse')
            ->assertSee('Mark damaged');

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('sendToWarehouse', $box->id)
            ->assertSet('errorMessage', null)
            ->assertSee('marked ready to ship', false);

        $box->refresh();
        $this->assertSame($this->kitchen->id, $box->kitchen_id);
        $this->assertSame($this->kitchen->id, $box->held_by_user_id);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_RUN_REQUESTED,
        ]);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'warehouse_run_requested',
            'performed_by' => $this->kitchen->id,
        ]);
    }

    public function test_kitchen_can_mark_box_damaged_and_send_on_damaged_path(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '0');

        $box = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('openDamage', $box->id)
            ->set('damageNotes', 'Cracked lid')
            ->call('confirmDamage')
            ->assertSet('errorMessage', null)
            ->assertSet('filter', 'damaged');

        $box->refresh();
        $this->assertSame('damaged', $box->asset_status);
        $this->assertTrue($box->isAtKitchen($this->kitchen->id));
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'marked_damaged_at_kitchen',
            'notes' => 'Cracked lid',
            'performed_by' => $this->kitchen->id,
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('sendToWarehouse', $box->id)
            ->assertSet('errorMessage', fn ($msg) => is_string($msg) && str_contains($msg, 'Send damaged'));

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->set('filter', 'damaged')
            ->call('sendDamagedToWarehouse', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertNull($box->kitchen_id);
        $this->assertNull($box->held_by_user_id);
        $this->assertSame('damaged', $box->asset_status);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'returned_damaged_to_warehouse',
        ]);
    }

    public function test_damaged_box_is_excluded_from_dispatch_inventory(): void
    {
        $order = $this->createAssignedOrder(1);
        $good = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
        ]);
        $damaged = $this->makeBox([
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
            'asset_status' => 'damaged',
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('markReady', $order->id);

        LunchRunFlow::riderAccept($this->rider, $order->fresh());

        $modal = Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id);

        $availableIds = collect($modal->get('availableBoxes'))->pluck('id')->all();
        $this->assertContains($good->id, $availableIds);
        $this->assertNotContains($damaged->id, $availableIds);
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
            ->assertSee('Mark ready')
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
            'order_status' => 'processing',
            'payment_status' => 'paid',
        ]);
        $order->orderGroup->orders()->attach($lowOrder->id);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.orders.active'))
            ->assertOk()
            ->assertSee('Box Low');

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('markReady', $order->id);

        LunchRunFlow::riderAccept($this->rider, $order->fresh());

        Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->assertSet('showModal', true)
            ->assertSet('requiredQuantity', 2)
            ->assertSet('errorMessage', null)
            ->call('toggleBox', $box1->id)
            ->call('toggleBox', $box2->id)
            ->call('dispatchOrder')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'packed',
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

        KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
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
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', [$box->id])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertSet('showModal', false);

        $box->refresh();
        $request = KitchenBoxRequest::query()->first();
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
        $this->assertNull($box->kitchen_id);
        $this->assertSame(KitchenBoxRequest::STATUS_PENDING, $request->status);
        $this->assertSame(1, (int) $request->allocated_qty);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'staged_for_kitchen_pickup',
        ]);
    }

    public function test_send_boxes_modal_lists_riders_even_when_kitchen_area_does_not_match(): void
    {
        $city = City::create(['name' => 'Dhaka']);
        $kitchenArea = Area::create(['name' => 'Banani', 'city_id' => $city->id]);
        $riderArea = Area::create(['name' => 'Mirpur', 'city_id' => $city->id]);

        $this->kitchen->update(['area_id' => $kitchenArea->id]);
        $this->rider->update(['area_id' => $riderArea->id]);
        if (Schema::hasTable('area_user')) {
            $this->rider->areas()->sync([$riderArea->id]);
        }

        $box = $this->makeBox([
            'asset_status' => 'at_middo_warehouse',
            'held_by_user_id' => null,
            'kitchen_id' => null,
        ]);

        $operationRole = Role::firstOrCreate(['name' => 'operation']);
        $operator = User::create([
            'first_name' => 'Ops',
            'last_name' => 'Two',
            'mobile' => '01710000005',
            'password' => 'password',
            'role_id' => $operationRole->id,
            'status' => 'active',
        ]);

        KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        $component = Livewire::actingAs($operator)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', [$box->id])
            ->set('selectedKitchenId', $this->kitchen->id);

        $riderIds = collect($component->get('riders'))->pluck('id')->all();
        $this->assertContains($this->rider->id, $riderIds);

        $riderRow = collect($component->get('riders'))->firstWhere('id', $this->rider->id);
        $this->assertNotNull($riderRow);
        $this->assertArrayHasKey('areas_label', $riderRow);
        $this->assertStringContainsString('Mirpur', (string) $riderRow['areas_label']);

        $kitchenRow = collect($component->get('kitchens'))->firstWhere('id', $this->kitchen->id);
        $this->assertNotNull($kitchenRow);
        $this->assertArrayHasKey('search', $kitchenRow);
        $this->assertStringContainsString('Requested 1', (string) $kitchenRow['subtitle']);

        $component
            ->set('selectedRiderId', $this->rider->id)
            ->call('save')
            ->assertSet('showModal', false)
            ->assertHasNoErrors();

        $box->refresh();
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
        $this->assertDatabaseHas('kitchen_box_request_boxes', [
            'middo_box_id' => $box->id,
            'rider_id' => $this->rider->id,
            'status' => 'ready_for_pickup',
        ]);
    }
}
