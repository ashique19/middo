<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\Dashboard;
use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Livewire\Shared\StaffAlertsPage;
use App\Models\KitchenBoxRequest;
use App\Models\KitchenBoxRequestBox;
use App\Models\MiddoBox;
use App\Models\Role;
use App\Models\User;
use App\Support\RiderPendingBoxes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RiderStagedBoxPendingRunTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $ops;

    protected User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $opsRole = Role::create(['name' => 'operation']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        Role::create(['name' => 'admin']);

        $this->kitchen = User::create([
            'first_name' => 'Tomato',
            'last_name' => 'Kitchen',
            'mobile' => '01720000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $this->ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01720000002',
            'password' => 'password',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'One',
            'mobile' => '01720000003',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
    }

    public function test_staged_warehouse_box_appears_on_rider_pending_run_and_dashboard_count(): void
    {
        KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-PEND-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', [$box->id])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('kitchen_box_request_boxes', [
            'middo_box_id' => $box->id,
            'rider_id' => $this->rider->id,
            'status' => KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP,
        ]);

        $this->assertSame(1, RiderPendingBoxes::countForRider($this->rider->id));

        Livewire::actingAs($this->rider)
            ->test(Dashboard::class)
            ->assertSee('Middo boxes pending run', false)
            ->assertSee('(1)', false);

        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('MB-PEND-1', false)
            ->assertSee('Ready for pickup at warehouse', false)
            ->assertSee('Accept', false)
            ->assertSee('Tomato Kitchen', false);

        $this->actingAs($this->rider)
            ->get(route('delivery.middo-boxes.pending-run'))
            ->assertOk()
            ->assertSee('MB-PEND-1', false)
            ->assertSee('Accept', false);

        Livewire::actingAs($this->rider)
            ->test(StaffAlertsPage::class)
            ->assertSee('Ops→kitchen box run', false)
            ->assertSee('Open pending box runs', false)
            ->assertSee(route('delivery.middo-boxes.pending-run'), false);
    }

    public function test_rider_sees_ops_kitchen_run_group_and_can_accept_all(): void
    {
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 2,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        $boxA = MiddoBox::create([
            'qr_code_id' => 'MB-RUN-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $boxB = MiddoBox::create([
            'qr_code_id' => 'MB-RUN-2',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', [$boxA->id, $boxB->id], $this->kitchen->id, $request->id)
            ->set('selectedRiderId', $this->rider->id)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->assertSee('Ops→kitchen run #'.$request->id, false)
            ->assertSee('Accept all (2)', false)
            ->assertSee('MB-RUN-1', false)
            ->assertSee('MB-RUN-2', false)
            ->call('acceptRunPickup', $request->id)
            ->assertSet('errorMessage', null);

        $this->assertSame($this->rider->id, (int) $boxA->fresh()->held_by_user_id);
        $this->assertSame($this->rider->id, (int) $boxB->fresh()->held_by_user_id);
        $this->assertSame(
            KitchenBoxRequestBox::STATUS_RIDER_ACCEPTED,
            KitchenBoxRequestBox::query()->where('middo_box_id', $boxA->id)->value('status')
        );
        $this->assertSame(
            KitchenBoxRequestBox::STATUS_RIDER_ACCEPTED,
            KitchenBoxRequestBox::query()->where('middo_box_id', $boxB->id)->value('status')
        );
    }
}
