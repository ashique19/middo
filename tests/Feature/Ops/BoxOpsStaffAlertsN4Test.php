<?php

namespace Tests\Feature\Ops;

use App\Livewire\Corporate\MiddoBoxesCustodyModal;
use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Livewire\Shared\StaffAlertsPage;
use App\Models\Area;
use App\Models\City;
use App\Models\KitchenBoxRequest;
use App\Models\MiddoBox;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoxOpsStaffAlertsN4Test extends TestCase
{
    use RefreshDatabase;

    protected Role $deliveryRole;

    protected Role $kitchenRole;

    protected Role $corporateRole;

    protected Role $operationRole;

    protected City $city;

    protected Area $gulshan;

    protected Area $mirpur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryRole = Role::create(['name' => 'delivery']);
        $this->kitchenRole = Role::create(['name' => 'kitchen']);
        $this->corporateRole = Role::create(['name' => 'corporate']);
        $this->operationRole = Role::create(['name' => 'operation']);
        $this->city = City::create(['name' => 'Dhaka']);
        $this->gulshan = Area::create(['name' => 'Gulshan', 'city_id' => $this->city->id]);
        $this->mirpur = Area::create(['name' => 'Mirpur', 'city_id' => $this->city->id]);
    }

    protected function makeRider(string $mobile, Area $area): User
    {
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => $area->name,
            'mobile' => $mobile,
            'password' => 'password',
            'role_id' => $this->deliveryRole->id,
            'status' => 'active',
            'city_id' => $this->city->id,
            'area_id' => $area->id,
        ]);
        $rider->areas()->sync([$area->id]);

        return $rider;
    }

    public function test_ops_assign_alerts_rider_and_kitchen(): void
    {
        $kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'Gulshan',
            'mobile' => '01780000001',
            'password' => 'password',
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'area_id' => $this->gulshan->id,
        ]);
        $rider = $this->makeRider('01780000002', $this->gulshan);
        $otherRider = $this->makeRider('01780000003', $this->mirpur);
        $operator = User::create([
            'first_name' => 'Ops',
            'last_name' => 'One',
            'mobile' => '01780000004',
            'password' => 'password',
            'role_id' => $this->operationRole->id,
            'status' => 'active',
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-OPS-N4-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'held_by_user_id' => null,
            'kitchen_id' => null,
            'total_uses_count' => 0,
        ]);

        KitchenBoxRequest::create([
            'kitchen_id' => $kitchen->id,
            'quantity' => 1,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $kitchen->id,
        ]);

        Livewire::actingAs($operator)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', [$box->id])
            ->set('selectedRiderId', $rider->id)
            ->set('selectedKitchenId', $kitchen->id)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $rider->id)
            ->where('type', StaffAlert::TYPE_OPS_TO_KITCHEN_BOX)
            ->where('meta->kitchen_id', $kitchen->id)
            ->exists());
        // Kitchen is alerted when the rider hands stock, not at stage time.
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $kitchen->id)
            ->where('type', StaffAlert::TYPE_OPS_TO_KITCHEN_BOX)
            ->exists());
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $otherRider->id)
            ->where('type', StaffAlert::TYPE_OPS_TO_KITCHEN_BOX)
            ->exists());

        Livewire::actingAs($rider)
            ->test(StaffAlertsPage::class)
            ->assertOk()
            ->assertSee('Ops→kitchen box run', false);
    }

    public function test_empty_box_ready_alerts_riders_in_corporate_area(): void
    {
        $corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Gulshan',
            'mobile' => '01780000005',
            'password' => 'password',
            'role_id' => $this->corporateRole->id,
            'status' => 'active',
            'city_id' => $this->city->id,
            'area_id' => $this->gulshan->id,
        ]);
        $gulshanRider = $this->makeRider('01780000006', $this->gulshan);
        $mirpurRider = $this->makeRider('01780000007', $this->mirpur);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-EMPTY-N4-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'held_by_user_id' => $corporate->id,
            'ready_for_pickup' => false,
            'total_uses_count' => 1,
        ]);

        Livewire::actingAs($corporate)
            ->test(MiddoBoxesCustodyModal::class)
            ->call('markReadyForPickup', $box->id);

        $this->assertTrue($box->fresh()->ready_for_pickup);
        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $gulshanRider->id)
            ->where('type', StaffAlert::TYPE_EMPTY_BOX_PICKUP)
            ->where('meta->box_id', $box->id)
            ->exists());
        $this->assertFalse(StaffAlert::query()
            ->where('user_id', $mirpurRider->id)
            ->where('type', StaffAlert::TYPE_EMPTY_BOX_PICKUP)
            ->exists());

        // Idempotent: already-ready does not create a second alert.
        Livewire::actingAs($corporate)
            ->test(MiddoBoxesCustodyModal::class)
            ->call('markReadyForPickup', $box->id);

        $this->assertSame(1, StaffAlert::query()
            ->where('user_id', $gulshanRider->id)
            ->where('type', StaffAlert::TYPE_EMPTY_BOX_PICKUP)
            ->where('meta->box_id', $box->id)
            ->count());

        Livewire::actingAs($gulshanRider)
            ->test(StaffAlertsPage::class)
            ->assertOk()
            ->assertSee('Empty box pickup', false);
    }
}
