<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Kitchen\BoxesAtKitchen;
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
        ]);
        $rider->areas()->sync([$this->gulshan->id]);

        return $rider;
    }

    public function test_toggle_off_hides_via_rider_and_keeps_direct_send(): void
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
            ->assertDontSee('Tag rider')
            ->call('sendToWarehouse', $box->id)
            ->assertSet('statusMessage', "{$box->qr_code_id} sent to Middo warehouse.");

        $box->refresh();
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
    }

    public function test_tag_rider_lists_riders_outside_kitchen_area(): void
    {
        MiddoSettings::set(MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER, '1');

        $kitchen = $this->makeKitchen();
        $otherArea = Area::create(['name' => 'Banani', 'city_id' => $this->city->id]);
        $outOfAreaRider = User::create([
            'first_name' => 'Out',
            'last_name' => 'Rider',
            'mobile' => '01790000099',
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'city_id' => $this->city->id,
            'area_id' => $otherArea->id,
        ]);
        $outOfAreaRider->areas()->sync([$otherArea->id]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-N5-AREA',
            'box_model_type' => 'standard_insulated',
            'kitchen_id' => $kitchen->id,
            'held_by_user_id' => $kitchen->id,
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('openViaRider', $box->id)
            ->assertSee('Out Rider')
            ->set('selectedRiderId', $outOfAreaRider->id)
            ->call('sendViaRider')
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'rider_id' => $outOfAreaRider->id,
            'status' => KitchenWarehouseHandoff::STATUS_READY_FOR_PICKUP,
        ]);
    }

    public function test_tag_rider_stages_then_accept_books_commission_and_rider_delivers(): void
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
            ->assertSee('Tag rider')
            ->call('openViaRider', $box->id)
            ->set('selectedRiderId', $rider->id)
            ->call('sendViaRider')
            ->assertSet('errorMessage', null)
            ->assertSee('tagged for', false);

        $box->refresh();
        $this->assertSame($kitchen->id, $box->held_by_user_id);
        $this->assertSame($kitchen->id, $box->kitchen_id);
        $this->assertSame('active', $box->asset_status);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'kitchen_id' => $kitchen->id,
            'rider_id' => $rider->id,
            'status' => KitchenWarehouseHandoff::STATUS_READY_FOR_PICKUP,
        ]);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'staged_for_warehouse_pickup',
            'performed_by' => $kitchen->id,
        ]);
        $this->assertSame(0, MiddoOperatingCost::query()
            ->where('reference_type', MiddoBox::class)
            ->where('reference_id', $box->id)
            ->where('run_type', DeliveryRunType::KITCHEN_TO_OPS)
            ->count());
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $rider->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $ops->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());

        $this->assertSame(1, RiderPendingBoxes::countForRider($rider->id));

        Livewire::actingAs($kitchen)
            ->test(BoxesAtKitchen::class)
            ->assertSee('Tagged for pickup', false)
            ->assertSee('Rider Gulshan', false)
            ->assertDontSee('Tag rider');

        Livewire::actingAs($rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('Ready for pickup at kitchen', false)
            ->assertSee('Accept custody', false)
            ->call('acceptKitchenReturn', $box->id)
            ->assertSet('errorMessage', null)
            ->assertSet('statusMessage', "{$box->qr_code_id} accepted — deliver to Middo warehouse.");

        $box->refresh();
        $this->assertSame($rider->id, $box->held_by_user_id);
        $this->assertNull($box->kitchen_id);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_RIDER_ACCEPTED,
        ]);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'rider_accepted_warehouse_return',
            'performed_by' => $rider->id,
        ]);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'dispatched_to_warehouse',
            'performed_by' => $rider->id,
        ]);
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
            ->assertSee('Return to Middo warehouse', false)
            ->assertSee('Deliver to warehouse', false)
            ->call('deliverToWarehouse', $box->id)
            ->assertSet('errorMessage', null)
            ->assertSet('statusMessage', "{$box->qr_code_id} delivered to Middo warehouse.");

        $box->refresh();
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
        $this->assertDatabaseHas('kitchen_warehouse_handoffs', [
            'middo_box_id' => $box->id,
            'status' => KitchenWarehouseHandoff::STATUS_DELIVERED,
        ]);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'returned_to_warehouse',
            'performed_by' => $rider->id,
        ]);

        Livewire::actingAs($rider)
            ->test(StaffAlertsPage::class)
            ->assertOk()
            ->assertSee('Kitchen→ops box run', false)
            ->assertSee('Open pending box runs', false)
            ->assertSee(route('delivery.middo-boxes.pending-run'), false);
    }
}
