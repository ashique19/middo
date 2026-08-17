<?php

namespace Tests\Feature\Ops;

use App\Livewire\Corporate\MiddoBoxesCustodyModal;
use App\Livewire\Delivery\CustomRuns as DeliveryCustomRuns;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Kitchen\ActiveOrders;
use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Livewire\Operation\CustomRuns as OpsCustomRuns;
use App\Livewire\Operation\MiddoBoxes;
use App\Livewire\Operation\RidersBoard;
use App\Models\Area;
use App\Models\City;
use App\Models\CustomRun;
use App\Models\KitchenBoxRequest;
use App\Models\KitchenWarehouseHandoff;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\MiddoSettings;
use App\Support\OrderKitchenAcceptance;
use App\Support\OrderTransition;
use App\Support\RiderPendingBoxes;
use App\Support\StaffAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsAssignRiderRunsTest extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $rider;

    protected User $otherRider;

    protected User $kitchen;

    protected User $corporate;

    protected MenuItem $menu;

    protected Area $gulshan;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'admin']);
        $city = City::create(['name' => 'Dhaka']);
        $this->gulshan = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'Assign', 'mobile' => '01980000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'A', 'mobile' => '01980000002',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
            'area_id' => $this->gulshan->id, 'rider_shift_status' => 'on',
        ]);
        $this->rider->areas()->sync([$this->gulshan->id]);
        $this->otherRider = User::create([
            'first_name' => 'Rider', 'last_name' => 'B', 'mobile' => '01980000003',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
            'rider_shift_status' => 'on',
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'A', 'mobile' => '01980000004',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
            'area_id' => $this->gulshan->id,
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'A', 'mobile' => '01980000005',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
            'area_id' => $this->gulshan->id,
        ]);
        $this->menu = MenuItem::create([
            'name' => 'Assign Thali', 'price' => 200, 'delivery_commission' => 40,
        ]);
    }

    public function test_ops_to_kitchen_box_is_assigned_and_hidden_from_other_riders(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-OPS-K',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'held_by_user_id' => null,
            'kitchen_id' => null,
            'total_uses_count' => 0,
        ]);
        KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', [$box->id])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertSame(1, RiderPendingBoxes::countForRider($this->rider->id));
        $this->assertSame(0, RiderPendingBoxes::countForRider($this->otherRider->id));

        Livewire::actingAs($this->otherRider)
            ->test(PendingBoxRuns::class)
            ->assertDontSee('MB-OPS-K');

        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('MB-OPS-K', false)
            ->assertSee('Ready for pickup at warehouse', false);
    }

    public function test_kitchen_to_ops_box_is_hidden_until_ops_assigns(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '1');

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-K-OPS',
            'box_model_type' => 'standard_insulated',
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('sendToWarehouse', $box->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(0, RiderPendingBoxes::countForRider($this->rider->id));
        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->assertDontSee('MB-K-OPS')
            ->call('claimKitchenReturn', $box->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'Ops assigns'));

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('openAssignRider', $box->id, 'kitchen_to_ops')
            ->set('assignRiderId', $this->rider->id)
            ->call('saveAssignRider')
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'rider_id' => $this->rider->id,
            'status' => KitchenWarehouseHandoff::STATUS_RUN_CLAIMED,
        ]);
        $this->assertSame(1, RiderPendingBoxes::countForRider($this->rider->id));
        $this->assertSame(0, RiderPendingBoxes::countForRider($this->otherRider->id));
    }

    public function test_lunch_is_hidden_until_ops_assigns_rider(): void
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'area_id' => $this->gulshan->id,
            'order_status' => OrderTransition::READY,
            'payment_status' => 'pending',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-ASSIGN',
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
            'area_id' => $this->gulshan->id,
        ]);
        $group->orders()->attach($order->id);
        StaffAlerts::notifyOpsLunchNeedsRider($order->fresh(['menuItem', 'orderGroup', 'area']));

        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $this->ops->id)
            ->where('type', StaffAlert::TYPE_LUNCH_DISPATCH)
            ->where('meta->order_id', $order->id)
            ->exists());
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $this->rider->id)
            ->where('type', StaffAlert::TYPE_LUNCH_DISPATCH)
            ->exists());

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->assertDontSee('#'.$order->id, false)
            ->call('acceptOrder', $order->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'Ops assigns'));

        Livewire::actingAs($this->ops)
            ->test(RidersBoard::class)
            ->set('tab', 'awaiting')
            ->assertSee('#'.$order->id, false)
            ->call('openLunchAssign', $order->id)
            ->set('assignLunchRiderId', $this->rider->id)
            ->call('confirmLunchAssign')
            ->assertSet('errorMessage', '');

        $this->assertSame($this->rider->id, (int) $order->fresh()->delivery_rider_id);
        $this->assertSame(OrderTransition::RIDER_ASSIGNED, $order->fresh()->order_status);

        Livewire::actingAs($this->otherRider)
            ->test(KitchenDispatches::class)
            ->assertDontSee('#'.$order->id, false);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->assertSee('#'.$order->id, false)
            ->assertSee('Waiting for kitchen to pack', false)
            ->assertDontSee('Accept run');
    }

    public function test_ops_can_pre_assign_lunch_rider_after_kitchen_accepts(): void
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'area_id' => $this->gulshan->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-PREASSIGN',
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
            'area_id' => $this->gulshan->id,
        ]);
        $group->orders()->attach($order->id);

        OrderKitchenAcceptance::markGroupOrdersProcessing($group, $this->kitchen->id);

        $this->assertSame(OrderTransition::PROCESSING, $order->fresh()->order_status);
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $this->ops->id)
            ->where('type', StaffAlert::TYPE_LUNCH_DISPATCH)
            ->where('meta->order_id', $order->id)
            ->exists());

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->assertDontSee('#'.$order->id, false);

        Livewire::actingAs($this->ops)
            ->test(RidersBoard::class)
            ->set('tab', 'awaiting')
            ->assertSee('#'.$order->id, false)
            ->assertSee('Kitchen-accepted', false)
            ->call('openLunchAssign', $order->id)
            ->set('assignLunchRiderId', $this->rider->id)
            ->call('confirmLunchAssign')
            ->assertSet('errorMessage', '');

        $fresh = $order->fresh();
        $this->assertSame($this->rider->id, (int) $fresh->delivery_rider_id);
        $this->assertSame(OrderTransition::PROCESSING, $fresh->order_status);

        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $this->rider->id)
            ->where('type', StaffAlert::TYPE_LUNCH_DISPATCH)
            ->where('body', 'like', '%still prepping%')
            ->exists());

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->assertSee('#'.$order->id, false)
            ->assertSee('Waiting for kitchen to pack', false);

        Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->assertSet('errorMessage', 'Mark this order ready first.');

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('markReady', $order->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(OrderTransition::RIDER_ASSIGNED, $order->fresh()->order_status);

        Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->assertSet('errorMessage', null);
    }

    public function test_kitchen_release_clears_pre_assigned_lunch_rider(): void
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'area_id' => $this->gulshan->id,
            'order_status' => OrderTransition::PROCESSING,
            'payment_status' => 'pending',
            'delivery_rider_id' => $this->rider->id,
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-RELEASE-RIDER',
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
            'area_id' => $this->gulshan->id,
        ]);
        $group->orders()->attach($order->id);

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('releaseGroup', $group->id)
            ->assertSet('errorMessage', null);

        $this->assertNull($group->fresh()->kitchen_id);
        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertNull($order->fresh()->delivery_rider_id);
    }

    public function test_empty_box_collect_is_hidden_until_ops_assigns(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-EMPTY',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'held_by_user_id' => $this->corporate->id,
            'ready_for_pickup' => false,
            'total_uses_count' => 1,
        ]);

        Livewire::actingAs($this->corporate)
            ->test(MiddoBoxesCustodyModal::class)
            ->call('markReadyForPickup', $box->id);

        $this->assertTrue($box->fresh()->ready_for_pickup);
        $this->assertSame(0, RiderPendingBoxes::countForRider($this->rider->id));

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->assertSee('Corporate → kitchen', false)
            ->call('openAssignRider', $box->id, 'empty_box')
            ->set('assignRiderId', $this->rider->id)
            ->set('assignKitchenId', $this->kitchen->id)
            ->call('saveAssignRider')
            ->assertSet('errorMessage', null);

        $this->assertSame($this->rider->id, (int) $box->fresh()->pickup_rider_id);
        $this->assertSame($this->kitchen->id, (int) $box->fresh()->return_kitchen_id);
        $this->assertSame(1, RiderPendingBoxes::countForRider($this->rider->id));
        $this->assertSame(0, RiderPendingBoxes::countForRider($this->otherRider->id));

        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('Collect empty box', false)
            ->call('collectEmptyBox', $box->id)
            ->assertSet('errorMessage', null);

        $this->assertSame($this->rider->id, (int) $box->fresh()->held_by_user_id);
        $this->assertFalse((bool) $box->fresh()->ready_for_pickup);
    }

    public function test_ops_can_assign_empty_box_before_corporate_marks_ready(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-EMPTY-EARLY',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'held_by_user_id' => $this->corporate->id,
            'ready_for_pickup' => false,
            'total_uses_count' => 1,
        ]);

        $this->assertSame(0, RiderPendingBoxes::countForRider($this->rider->id));

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->assertSee('MB-EMPTY-EARLY', false)
            ->assertSee('Not marked ready', false)
            ->call('openAssignRider', $box->id, 'empty_box')
            ->set('assignRiderId', $this->rider->id)
            ->set('assignKitchenId', $this->kitchen->id)
            ->call('saveAssignRider')
            ->assertSet('errorMessage', null);

        $this->assertSame($this->rider->id, (int) $box->fresh()->pickup_rider_id);
        $this->assertFalse((bool) $box->fresh()->ready_for_pickup);
        $this->assertSame(1, RiderPendingBoxes::countForRider($this->rider->id));

        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('Collect empty box', false)
            ->call('collectEmptyBox', $box->id)
            ->assertSet('errorMessage', null);

        $this->assertSame($this->rider->id, (int) $box->fresh()->held_by_user_id);
        $this->assertFalse((bool) $box->fresh()->ready_for_pickup);
    }

    public function test_custom_run_requires_rider_and_is_hidden_from_others(): void
    {
        Livewire::actingAs($this->ops)
            ->test(OpsCustomRuns::class)
            ->set('fromLabel', 'Warehouse')
            ->set('toLabel', 'Kitchen')
            ->set('commissionAmount', 25)
            ->call('createRun')
            ->assertHasErrors(['riderUserId']);

        $this->assertSame(0, CustomRun::query()->count());

        Livewire::actingAs($this->ops)
            ->test(OpsCustomRuns::class)
            ->set('fromLabel', 'Warehouse')
            ->set('toLabel', 'Kitchen')
            ->set('riderUserId', $this->rider->id)
            ->set('commissionAmount', 25)
            ->call('createRun')
            ->assertSet('errorMessage', '');

        $run = CustomRun::query()->firstOrFail();
        $this->assertSame($this->rider->id, (int) $run->rider_user_id);

        Livewire::actingAs($this->otherRider)
            ->test(DeliveryCustomRuns::class)
            ->assertDontSee('Warehouse → Kitchen', false)
            ->call('startRun', $run->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'not assigned'));

        Livewire::actingAs($this->rider)
            ->test(DeliveryCustomRuns::class)
            ->assertSee('Warehouse → Kitchen', false)
            ->call('startRun', $run->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(CustomRun::STATUS_STARTED, $run->fresh()->status);
    }
}
