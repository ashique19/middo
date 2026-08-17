<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Delivery\Dashboard;
use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Operation\MiddoBoxes;
use App\Livewire\Shared\StaffAlertsPage;
use App\Models\Area;
use App\Models\City;
use App\Models\KitchenWarehouseHandoff;
use App\Models\MiddoBox;
use App\Models\MiddoOperatingCost;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoSettings;
use App\Support\RiderPendingBoxes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenToOpsRiderLegN5Test extends TestCase
{
    use RefreshDatabase;

    protected Role $kitchenRole;

    protected Role $deliveryRole;

    protected Role $operationRole;

    protected City $city;

    protected Area $gulshan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kitchenRole = Role::create(['name' => 'kitchen']);
        $this->deliveryRole = Role::create(['name' => 'delivery']);
        $this->operationRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $this->city = City::create(['name' => 'Dhaka']);
        $this->gulshan = Area::create(['name' => 'Gulshan', 'city_id' => $this->city->id]);
    }

    protected function makeKitchen(): User
    {
        return User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Gulshan',
            'mobile' => '01790000001',
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'area_id' => $this->gulshan->id,
        ]);
    }

    protected function makeRider(string $mobile = '01790000002'): User
    {
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Gulshan',
            'mobile' => $mobile,
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'city_id' => $this->city->id,
            'area_id' => $this->gulshan->id,
            'rider_shift_status' => 'on',
        ]);
        $rider->areas()->sync([$this->gulshan->id]);

        return $rider;
    }

    public function test_toggle_off_keeps_direct_teleport_send(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '0');
        $kitchen = $this->makeKitchen();
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-N5-DIRECT',
            'box_model_type' => 'standard_insulated',
            'kitchen_id' => $kitchen->id,
            'held_by_user_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('sendToWarehouse', $box->id)
            ->assertSet('statusMessage', "{$box->qr_code_id} sent to Middo warehouse.");

        $box->refresh();
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
        $this->assertDatabaseMissing('kitchen_warehouse_handoffs', ['middo_box_id' => $box->id]);
    }

    public function test_ready_to_ship_ops_assign_dispatch_accept_hand_ops_receive(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '1');
        MiddoSettings::set('delivery.commission.'.DeliveryRunType::KITCHEN_TO_OPS, '33');

        $kitchen = $this->makeKitchen();
        $rider = $this->makeRider();
        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'One',
            'mobile' => '01790000003',
            'password' => 'password',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-N5-VIA',
            'box_model_type' => 'standard_insulated',
            'kitchen_id' => $kitchen->id,
            'held_by_user_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('sendToWarehouse', $box->id)
            ->assertSet('errorMessage', null)
            ->assertSee('marked ready to ship', false);

        $box->refresh();
        $this->assertSame($kitchen->id, $box->held_by_user_id);
        $this->assertSame($kitchen->id, $box->kitchen_id);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'kitchen_id' => $kitchen->id,
            'rider_id' => null,
            'status' => KitchenWarehouseHandoff::STATUS_RUN_REQUESTED,
        ]);
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $ops->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->where('title', 'Assign kitchen→ops rider')
            ->exists());
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $rider->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());
        $this->assertSame(0, RiderPendingBoxes::countForRider($rider->id));
        $this->assertSame(0, RiderPendingBoxes::claimableKitchenToOpsCount());

        Livewire::actingAs($rider)
            ->test(PendingBoxRuns::class)
            ->assertDontSee('Claim run')
            ->call('claimKitchenReturn', $box->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'Ops assigns'));

        Livewire::actingAs($ops)
            ->test(MiddoBoxes::class)
            ->assertSee('Assign rider', false)
            ->call('openAssignRider', $box->id, 'kitchen_to_ops')
            ->set('assignRiderId', $rider->id)
            ->call('saveAssignRider')
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'rider_id' => $rider->id,
            'status' => KitchenWarehouseHandoff::STATUS_RUN_CLAIMED,
        ]);
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $kitchen->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $rider->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->assertSee('Rider assigned', false)
            ->assertSee('Dispatch to', false)
            ->call('dispatchWarehouseRun', $box->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_DISPATCHED,
        ]);

        Livewire::actingAs($rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('Accept box & start run', false)
            ->call('acceptKitchenReturn', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertSame($rider->id, $box->held_by_user_id);
        $this->assertNull($box->kitchen_id);
        $this->assertSame(1, MiddoOperatingCost::query()
            ->where('reference_type', MiddoBox::class)
            ->where('reference_id', $box->id)
            ->where('run_type', DeliveryRunType::KITCHEN_TO_OPS)
            ->count());
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $ops->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());

        Livewire::actingAs($rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('Hand to Middo ops', false)
            ->call('deliverToWarehouse', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertSame($rider->id, $box->held_by_user_id);
        $this->assertSame('active', $box->asset_status);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS,
        ]);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'handed_to_ops_warehouse',
        ]);
        $this->assertSame(1, RiderPendingBoxes::countForRider($rider->id));

        Livewire::actingAs($rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('awaiting ops confirm receive', false)
            ->assertDontSee('Hand to Middo ops');

        Livewire::actingAs($ops)
            ->test(MiddoBoxes::class)
            ->set('custodyFilter', 'returns')
            ->assertSee('Confirm receive', false)
            ->call('ackReturn', $box->id)
            ->assertSee('Confirmed receive', false);

        $box->refresh();
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_RECEIVED,
        ]);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'ops_acked_warehouse_return',
            'performed_by' => $ops->id,
        ]);
        $this->assertSame(0, RiderPendingBoxes::countForRider($rider->id));

        Livewire::actingAs($rider)
            ->test(StaffAlertsPage::class)
            ->assertOk()
            ->assertSee('Assigned kitchen→ops run', false)
            ->assertSee('Open pending box runs', false);
    }

    public function test_ops_can_assign_rider_outside_kitchen_area(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '1');

        $kitchen = $this->makeKitchen();
        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'Any',
            'mobile' => '01790000088',
            'password' => 'password',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
        ]);
        $otherArea = Area::create(['name' => 'Mirpur Test', 'city_id' => $this->city->id]);
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Elsewhere',
            'mobile' => '01790000099',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'city_id' => $this->city->id,
            'area_id' => $otherArea->id,
            'rider_shift_status' => 'on',
        ]);
        $rider->areas()->sync([$otherArea->id]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-N5-ANY',
            'box_model_type' => 'standard_insulated',
            'kitchen_id' => $kitchen->id,
            'held_by_user_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('sendToWarehouse', $box->id)
            ->assertSet('errorMessage', null);

        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $rider->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());
        $this->assertSame(0, RiderPendingBoxes::countForRider($rider->id));

        Livewire::actingAs($rider)
            ->test(Dashboard::class)
            ->assertSet('claimableKitchenToOpsCount', 0)
            ->assertDontSee('waiting to claim');

        Livewire::actingAs($ops)
            ->test(MiddoBoxes::class)
            ->call('openAssignRider', $box->id, 'kitchen_to_ops')
            ->set('assignRiderId', $rider->id)
            ->call('saveAssignRider')
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'rider_id' => $rider->id,
            'status' => KitchenWarehouseHandoff::STATUS_RUN_CLAIMED,
        ]);
        $this->assertSame(1, RiderPendingBoxes::countForRider($rider->id));
    }

    public function test_damaged_box_uses_via_rider_ops_assign_dispatch_accept_hand(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '1');
        MiddoSettings::set('delivery.commission.'.DeliveryRunType::KITCHEN_TO_OPS, '21');

        $kitchen = $this->makeKitchen();
        $rider = $this->makeRider('01790000111');
        $ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'Damaged',
            'mobile' => '01790000112',
            'password' => 'password',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-N5-DMG',
            'box_model_type' => 'standard_insulated',
            'kitchen_id' => $kitchen->id,
            'held_by_user_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('openDamage', $box->id)
            ->set('damageNotes', 'Broken hinge')
            ->call('confirmDamage')
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertSame('damaged', $box->asset_status);

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->set('filter', 'damaged')
            ->call('sendDamagedToWarehouse', $box->id)
            ->assertSet('errorMessage', null)
            ->assertSee('marked ready to ship', false);

        $box->refresh();
        $this->assertSame('damaged', $box->asset_status);
        $this->assertSame($kitchen->id, $box->kitchen_id);
        $this->assertSame($kitchen->id, $box->held_by_user_id);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_RUN_REQUESTED,
            'rider_id' => null,
        ]);
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $rider->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());
        $this->assertSame(0, RiderPendingBoxes::claimableKitchenToOpsCount());
        $this->assertSame(0, RiderPendingBoxes::countForRider($rider->id));

        Livewire::actingAs($ops)
            ->test(MiddoBoxes::class)
            ->call('openAssignRider', $box->id, 'kitchen_to_ops')
            ->set('assignRiderId', $rider->id)
            ->call('saveAssignRider')
            ->assertSet('errorMessage', null);

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->set('filter', 'damaged')
            ->assertSee('Dispatch to', false)
            ->call('dispatchWarehouseRun', $box->id)
            ->assertSet('errorMessage', null);

        Livewire::actingAs($rider)
            ->test(PendingBoxRuns::class)
            ->call('acceptKitchenReturn', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertSame($rider->id, $box->held_by_user_id);
        $this->assertSame('damaged', $box->asset_status);
        $this->assertSame(1, RiderPendingBoxes::countForRider($rider->id));

        Livewire::actingAs($rider)
            ->test(PendingBoxRuns::class)
            ->call('deliverToWarehouse', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertSame($rider->id, $box->held_by_user_id);
        $this->assertSame('damaged', $box->asset_status);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'handed_to_ops_warehouse',
        ]);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS,
        ]);
        $this->assertSame(1, RiderPendingBoxes::countForRider($rider->id));

        Livewire::actingAs($ops)
            ->test(MiddoBoxes::class)
            ->set('custodyFilter', 'returns')
            ->call('ackReturn', $box->id)
            ->assertSee('Confirmed receive', false);

        $box->refresh();
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'returned_damaged_to_warehouse',
        ]);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_RECEIVED,
        ]);
    }
}
