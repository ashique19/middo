<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\ActiveOrders;
use App\Livewire\Shared\OrderShow;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderMiddoBox;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderMoneyFlow;
use App\Support\OrderOpsForce;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsOrderForceO5Test extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $corporate;

    protected User $kitchen;

    protected User $rider;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'O5', 'mobile' => '01980000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'O5', 'mobile' => '01980000002',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
            'balance' => 500,
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'O5', 'mobile' => '01980000003',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
            'kitchen_tier' => 'gold',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'O5', 'mobile' => '01980000004',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'O5 Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);
    }

    public function test_ops_can_cancel_processing_order_with_wallet_refund(): void
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'amount_paid' => 200,
            'address' => 'HQ',
            'order_status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-O5-CXL',
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        Livewire::actingAs($this->ops)
            ->test(OrderShow::class, ['order' => $order])
            ->assertSee('Ops force tools')
            ->set('forceReason', 'Customer called off')
            ->call('forceCancel')
            ->assertSet('forceError', null)
            ->assertSee('Wallet refunded');

        $order->refresh();
        $this->assertSame(OrderTransition::CANCELLED, $order->order_status);
        $this->assertSame(700, (int) $this->corporate->fresh()->balance);
        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'event' => 'ops_force_status',
            'performed_by' => $this->ops->id,
        ]);
    }

    public function test_ops_release_rider_returns_to_packed_and_voids_delivery_share(): void
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => OrderTransition::ON_THE_WAY_TO_DELIVERY,
            'payment_status' => 'pending',
            'dispatched_at' => now(),
            'delivery_rider_id' => $this->rider->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-O5-REL',
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-O5-REL',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'held_by_user_id' => $this->rider->id,
            'kitchen_id' => null,
            'total_uses_count' => 1,
        ]);
        OrderMiddoBox::create([
            'order_id' => $order->id,
            'middo_box_id' => $box->id,
        ]);

        OrderMoneyFlow::accrueDeliveryShareOnRunStart($order->fresh(['menuItem', 'orderGroup']), $this->rider);
        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));
        $this->assertDatabaseHas('partner_payables', [
            'order_id' => $order->id,
            'beneficiary_role' => PartnerPayable::ROLE_DELIVERY,
            'status' => PartnerPayable::STATUS_OPEN,
        ]);

        Livewire::actingAs($this->ops)
            ->test(OrderShow::class, ['order' => $order])
            ->assertSee('Release rider')
            ->call('releaseRider')
            ->assertSet('forceError', null);

        $order->refresh();
        $this->assertSame(OrderTransition::PACKED, $order->order_status);
        $this->assertNull($order->delivery_rider_id);
        $this->assertSame(0, RiderAccountLedger::balance($this->rider->id));
        $this->assertDatabaseHas('partner_payables', [
            'order_id' => $order->id,
            'beneficiary_role' => PartnerPayable::ROLE_DELIVERY,
            'status' => PartnerPayable::STATUS_VOID,
        ]);

        $box->refresh();
        $this->assertSame($this->kitchen->id, $box->held_by_user_id);
        $this->assertSame($this->kitchen->id, $box->kitchen_id);
    }

    public function test_cannot_cancel_packed_order_via_ops_force(): void
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'packed',
            'payment_status' => 'pending',
            'dispatched_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        OrderOpsForce::cancelBeforePacked($order, $this->ops, 'nope');
    }

    public function test_active_orders_filters_and_bulk_kitchen_assign(): void
    {
        $kitchenB = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'B', 'mobile' => '01980000005',
            'password' => 'password', 'role_id' => $this->kitchen->role_id, 'status' => 'active',
            'kitchen_tier' => 'gold',
        ]);

        $pending = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'A',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $packed = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '1:00 PM',
            'total_amount' => 200,
            'address' => 'B',
            'order_status' => 'packed',
            'payment_status' => 'pending',
            'dispatched_at' => now(),
        ]);

        $unassignedGroup = OrderGroup::create([
            'name' => 'GRP-UNASSIGNED',
            'menu_id' => $this->menu->id,
            'delivery_date' => $pending->delivery_date,
            'kitchen_id' => null,
        ]);
        $unassignedGroup->orders()->attach($pending->id);

        $assignedGroup = OrderGroup::create([
            'name' => 'GRP-ASSIGNED',
            'menu_id' => $this->menu->id,
            'delivery_date' => $packed->delivery_date,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $assignedGroup->orders()->attach($packed->id);

        Livewire::actingAs($this->ops)
            ->test(ActiveOrders::class)
            ->assertSee('GRP-UNASSIGNED')
            ->assertSee('GRP-ASSIGNED')
            ->set('statusFilter', 'packed')
            ->assertDontSee('GRP-UNASSIGNED')
            ->assertSee('GRP-ASSIGNED')
            ->set('statusFilter', 'all')
            ->set('awaitingRiderOnly', true)
            ->assertSee('GRP-ASSIGNED')
            ->assertDontSee('GRP-UNASSIGNED')
            ->set('awaitingRiderOnly', false)
            ->set('kitchenFilter', 'unassigned')
            ->assertSee('GRP-UNASSIGNED')
            ->assertDontSee('GRP-ASSIGNED')
            ->set('bulkKitchenId', $kitchenB->id)
            ->call('bulkAssignUnassignedKitchen')
            ->assertSee('Assigned 1 unassigned');

        $this->assertSame($kitchenB->id, $unassignedGroup->fresh()->kitchen_id);
        $this->assertSame('processing', $pending->fresh()->order_status);
    }
}
