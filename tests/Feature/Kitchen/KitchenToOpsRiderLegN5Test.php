<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Shared\StaffAlertsPage;
use App\Models\Area;
use App\Models\City;
use App\Models\MiddoBox;
use App\Models\MiddoOperatingCost;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoSettings;
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
            ->assertDontSee('Send via rider')
            ->call('sendToWarehouse', $box->id)
            ->assertSet('statusMessage', "{$box->qr_code_id} sent to Middo warehouse.");

        $box->refresh();
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
    }

    public function test_via_rider_books_commission_alerts_and_rider_can_deliver(): void
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
            ->assertSee('Send via rider')
            ->call('openViaRider', $box->id)
            ->set('selectedRiderId', $rider->id)
            ->call('sendViaRider')
            ->assertSet('errorMessage', null)
            ->assertSet('statusMessage', "{$box->qr_code_id} handed to rider for Middo warehouse.");

        $box->refresh();
        $this->assertSame($rider->id, $box->held_by_user_id);
        $this->assertNull($box->kitchen_id);
        $this->assertSame('active', $box->asset_status);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'dispatched_to_warehouse',
            'performed_by' => $kitchen->id,
        ]);
        $this->assertSame(1, MiddoOperatingCost::query()
            ->where('reference_type', MiddoBox::class)
            ->where('reference_id', $box->id)
            ->where('run_type', DeliveryRunType::KITCHEN_TO_OPS)
            ->count());
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $rider->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_TO_OPS_BOX)
            ->exists());
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
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'returned_to_warehouse',
            'performed_by' => $rider->id,
        ]);

        Livewire::actingAs($rider)
            ->test(StaffAlertsPage::class)
            ->assertOk()
            ->assertSee('Kitchen→ops box run', false);
    }
}
