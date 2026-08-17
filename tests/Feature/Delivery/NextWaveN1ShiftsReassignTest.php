<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\Dashboard;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Operation\RidersBoard;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoOperatingCost;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderMiddoBox;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoSettings;
use App\Support\OpsRiderMidRunReassign;
use App\Support\OrderMoneyFlow;
use App\Support\OrderOpsForce;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use App\Support\RiderShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NextWaveN1ShiftsReassignTest extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $corporate;

    protected User $kitchen;

    protected User $riderA;

    protected User $riderB;

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
            'first_name' => 'Ops', 'last_name' => 'N1', 'mobile' => '01991000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'N1', 'mobile' => '01991000002',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'N1', 'mobile' => '01991000003',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
            'kitchen_tier' => 'gold',
        ]);
        $this->riderA = User::create([
            'first_name' => 'Rider', 'last_name' => 'A', 'mobile' => '01991000004',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
            'rider_shift_status' => RiderShift::ON,
        ]);
        $this->riderB = User::create([
            'first_name' => 'Rider', 'last_name' => 'B', 'mobile' => '01991000005',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
            'rider_shift_status' => RiderShift::ON,
        ]);
        $this->menu = MenuItem::create([
            'name' => 'N1 Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);
    }

    protected function makeOnTheWayOrder(User $rider): Order
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
            'delivery_rider_id' => $rider->id,
            'original_delivery_rider_id' => $rider->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-N1-'.$order->id,
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-N1-'.$order->id,
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'held_by_user_id' => $rider->id,
            'kitchen_id' => null,
            'total_uses_count' => 1,
        ]);
        OrderMiddoBox::create([
            'order_id' => $order->id,
            'middo_box_id' => $box->id,
        ]);

        OrderMoneyFlow::accrueDeliveryShareOnRunStart($order->fresh(['menuItem', 'orderGroup']), $rider);

        return $order->fresh(['middoBoxes']);
    }

    public function test_rider_can_toggle_shift_and_off_blocks_accept(): void
    {
        Livewire::actingAs($this->riderA)
            ->test(Dashboard::class)
            ->call('setShift', RiderShift::OFF)
            ->assertSet('shiftStatus', RiderShift::OFF)
            ->assertSet('errorMessage', null);

        $this->assertSame(RiderShift::OFF, $this->riderA->fresh()->rider_shift_status);
        $this->assertFalse($this->riderA->fresh()->canAcceptNewRuns());

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'packed',
            'payment_status' => 'pending',
            'dispatched_at' => now(),
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-N1-OFF',
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-N1-OFF',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'held_by_user_id' => $this->kitchen->id,
            'kitchen_id' => $this->kitchen->id,
            'total_uses_count' => 0,
        ]);
        OrderMiddoBox::create(['order_id' => $order->id, 'middo_box_id' => $box->id]);

        Livewire::actingAs($this->riderA)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'Ops assigns'));

        $this->assertNull($order->fresh()->delivery_rider_id);
    }

    public function test_mid_run_reassign_keeps_starter_commission_and_moves_boxes(): void
    {
        $order = $this->makeOnTheWayOrder($this->riderA);
        $this->assertSame(40, RiderAccountLedger::balance($this->riderA->id));
        $this->assertSame(0, RiderAccountLedger::balance($this->riderB->id));

        Livewire::actingAs($this->ops)
            ->test(RidersBoard::class)
            ->set('tab', 'on_the_way')
            ->call('openOrderReassign', $order->id)
            ->set('reassignOrderRiderId', $this->riderB->id)
            ->set('reassignOrderReason', 'Vehicle damaged')
            ->call('confirmOrderReassign')
            ->assertSet('errorMessage', '')
            ->assertSee('reassigned');

        $order->refresh();
        $this->assertSame($this->riderB->id, (int) $order->delivery_rider_id);
        $this->assertSame($this->riderA->id, (int) $order->original_delivery_rider_id);

        $box = $order->middoBoxes()->first();
        $this->assertSame($this->riderB->id, (int) $box->held_by_user_id);

        $this->assertSame(40, RiderAccountLedger::balance($this->riderA->id));
        $this->assertSame(0, RiderAccountLedger::balance($this->riderB->id));
        $this->assertDatabaseHas('partner_payables', [
            'order_id' => $order->id,
            'beneficiary_role' => PartnerPayable::ROLE_DELIVERY,
            'beneficiary_user_id' => $this->riderA->id,
            'status' => PartnerPayable::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'event' => 'ops_mid_run_reassign',
            'performed_by' => $this->ops->id,
        ]);
        $this->assertDatabaseHas('middo_box_logs', [
            'order_id' => $order->id,
            'log_action' => 'ops_mid_run_reassigned',
        ]);

        // Accrue is order-idempotent — B does not get a second lunch commission.
        OrderMoneyFlow::accrueDeliveryShareOnRunStart($order->fresh(['menuItem', 'orderGroup']), $this->riderB);
        $this->assertSame(0, RiderAccountLedger::balance($this->riderB->id));
        $this->assertSame(40, RiderAccountLedger::balance($this->riderA->id));
    }

    public function test_mid_run_reassign_books_optional_rescue_rate_for_b(): void
    {
        MiddoSettings::updateMealAndKitchenDefaults(['mid_run_rescue_commission' => 15]);
        $order = $this->makeOnTheWayOrder($this->riderA);

        OpsRiderMidRunReassign::reassign($order, $this->riderB, $this->ops, 'rescue pay stub');

        $this->assertSame(40, RiderAccountLedger::balance($this->riderA->id));
        $this->assertSame(15, RiderAccountLedger::balance($this->riderB->id));
        $this->assertSame(1, MiddoOperatingCost::query()
            ->where('run_type', DeliveryRunType::MID_RUN_RESCUE)
            ->where('rider_user_id', $this->riderB->id)
            ->where('reference_id', $order->id)
            ->count());
    }

    public function test_mid_run_reassign_blocked_after_cash_collected(): void
    {
        $order = $this->makeOnTheWayOrder($this->riderA);
        $order->update(['cash_collected' => 200]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('after cash has been collected');
        OpsRiderMidRunReassign::reassign($order, $this->riderB, $this->ops);
    }

    public function test_ops_release_still_voids_starter_commission(): void
    {
        $order = $this->makeOnTheWayOrder($this->riderA);
        $this->assertSame(40, RiderAccountLedger::balance($this->riderA->id));

        OrderOpsForce::releaseRiderToPacked($order, $this->ops, 'failed delivery');

        $this->assertSame(0, RiderAccountLedger::balance($this->riderA->id));
        $this->assertSame(OrderTransition::PACKED, $order->fresh()->order_status);
        $this->assertNull($order->fresh()->delivery_rider_id);
    }

    public function test_cannot_reassign_to_off_shift_rider(): void
    {
        $order = $this->makeOnTheWayOrder($this->riderA);
        $this->riderB->update(['rider_shift_status' => RiderShift::OFF]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('on shift');
        OpsRiderMidRunReassign::reassign($order, $this->riderB->fresh(), $this->ops);
    }
}
