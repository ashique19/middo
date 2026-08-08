<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Delivery\PendingBoxRuns;
use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Kitchen\IncomingBoxes;
use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Livewire\Operation\MiddoBoxes;
use App\Models\KitchenBoxRequest;
use App\Models\KitchenBoxRequestBox;
use App\Models\KitchenBoxRequestLog;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenBoxRequestTest extends TestCase
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
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01718000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'User',
            'mobile' => '01718000002',
            'password' => 'password',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);

        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'One',
            'mobile' => '01718000003',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
    }

    public function test_request_query_opens_box_request_modal(): void
    {
        Livewire::actingAs($this->kitchen)
            ->withQueryParams(['request' => 1])
            ->test(BoxesAtKitchen::class)
            ->assertSet('showRequestModal', true);
    }

    public function test_kitchen_can_request_boxes_and_ops_sees_pending_request(): void
    {
        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('openRequestModal')
            ->assertSet('showRequestModal', true)
            ->set('requestQuantity', 4)
            ->set('requestNote', 'Need stock for lunch')
            ->call('submitBoxRequest')
            ->assertSet('showRequestModal', false)
            ->assertSet('errorMessage', null)
            ->assertSee('Requested 4 Middo boxes', false)
            ->assertSee('Pending box requests', false)
            ->assertSee('Need stock for lunch', false);

        $this->assertDatabaseHas('kitchen_box_requests', [
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 4,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'note' => 'Need stock for lunch',
            'requested_by' => $this->kitchen->id,
        ]);

        $request = KitchenBoxRequest::query()->first();
        $this->assertDatabaseHas('kitchen_box_request_logs', [
            'kitchen_box_request_id' => $request->id,
            'event' => KitchenBoxRequestLog::EVENT_REQUESTED,
            'performed_by' => $this->kitchen->id,
        ]);

        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $this->ops->id)
            ->where('type', StaffAlert::TYPE_KITCHEN_BOX_REQUEST)
            ->where('meta->kitchen_id', $this->kitchen->id)
            ->exists());

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->assertSee('Kitchen box requests', false)
            ->assertSee('Gulshan Kitchen', false)
            ->assertSee('Need stock for lunch', false)
            ->assertSee('4', false);
    }

    public function test_ops_can_close_request_with_note_after_kitchen_receives(): void
    {
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-REQ-CLOSE',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', ['boxIds' => [$box->id]])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->call('acceptWarehouseStock', $box->id)
            ->assertSet('errorMessage', null)
            ->call('handWarehouseStock', $box->id)
            ->assertSet('errorMessage', null);

        Livewire::actingAs($this->kitchen)
            ->test(IncomingBoxes::class)
            ->call('receiveBox', $box->id)
            ->assertSet('errorMessage', null);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('openCloseRequest', $request->id)
            ->set('closeNote', 'Kitchen confirmed receipt')
            ->call('closeBoxRequest')
            ->assertSet('errorMessage', null)
            ->assertDontSee('Kitchen box requests', false);

        $request->refresh();
        $this->assertSame(KitchenBoxRequest::STATUS_CLOSED, $request->status);
        $this->assertSame('Kitchen confirmed receipt', $request->closed_note);
        $this->assertSame($this->ops->id, (int) $request->closed_by);
        $this->assertNotNull($request->closed_at);
        $this->assertDatabaseHas('kitchen_box_request_logs', [
            'kitchen_box_request_id' => $request->id,
            'event' => KitchenBoxRequestLog::EVENT_CLOSED,
        ]);
    }

    public function test_kitchen_can_cancel_own_pending_request(): void
    {
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 2,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('cancelBoxRequest', $request->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(KitchenBoxRequest::STATUS_CANCELLED, $request->fresh()->status);
    }

    public function test_request_quantity_is_validated(): void
    {
        Livewire::actingAs($this->kitchen)
            ->test(BoxesAtKitchen::class)
            ->call('openRequestModal')
            ->set('requestQuantity', 0)
            ->call('submitBoxRequest')
            ->assertHasErrors(['requestQuantity']);

        $this->assertSame(0, KitchenBoxRequest::query()->count());
    }

    public function test_ops_cannot_send_boxes_without_pending_request(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-REQ-001',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', ['boxIds' => [$box->id]])
            ->assertSet('kitchens', [])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertHasErrors(['selectedKitchenId']);

        $this->assertSame('at_middo_warehouse', $box->fresh()->asset_status);
    }

    public function test_ops_cannot_send_more_boxes_than_requested(): void
    {
        KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        $boxA = MiddoBox::create([
            'qr_code_id' => 'MB-REQ-A',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $boxB = MiddoBox::create([
            'qr_code_id' => 'MB-REQ-B',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', ['boxIds' => [$boxA->id, $boxB->id]])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertHasErrors(['selectedKitchenId']);

        $this->assertSame('at_middo_warehouse', $boxA->fresh()->asset_status);
        $this->assertSame(1, KitchenBoxRequest::pendingQuantityForKitchen($this->kitchen->id));
    }

    public function test_ops_stage_partially_increments_allocated_without_rider_custody(): void
    {
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 3,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-REQ-P',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', ['boxIds' => [$box->id]])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertSet('showModal', false)
            ->assertHasNoErrors();

        $request->refresh();
        $box->refresh();

        $this->assertSame(KitchenBoxRequest::STATUS_PENDING, $request->status);
        $this->assertSame(3, $request->quantity);
        $this->assertSame(1, $request->allocated_qty);
        $this->assertSame(2, $request->remainingQuantity());
        $this->assertSame('at_middo_warehouse', $box->asset_status);
        $this->assertNull($box->held_by_user_id);
        $this->assertNull($box->kitchen_id);

        $this->assertDatabaseHas('kitchen_box_request_boxes', [
            'kitchen_box_request_id' => $request->id,
            'middo_box_id' => $box->id,
            'rider_id' => $this->rider->id,
            'status' => KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP,
        ]);

        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'staged_for_kitchen_pickup',
        ]);
    }

    public function test_full_handoff_lifecycle_is_logged(): void
    {
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);
        KitchenBoxRequestLog::create([
            'kitchen_box_request_id' => $request->id,
            'event' => KitchenBoxRequestLog::EVENT_REQUESTED,
            'performed_by' => $this->kitchen->id,
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-REQ-FLOW',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', ['boxIds' => [$box->id]])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save');

        // Staged-only stock is not incoming yet (still at warehouse).
        Livewire::actingAs($this->kitchen)
            ->test(IncomingBoxes::class)
            ->assertDontSee('MB-REQ-FLOW', false)
            ->call('receiveBox', $box->id)
            ->assertSet('errorMessage', 'This box is not incoming to your kitchen.');

        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->call('acceptWarehouseStock', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertSame($this->rider->id, (int) $box->held_by_user_id);
        $this->assertSame($this->kitchen->id, (int) $box->kitchen_id);
        $this->assertSame('rider_accepted_kitchen_stock', MiddoBoxLog::query()->where('middo_box_id', $box->id)->latest('id')->value('log_action'));

        // After rider accepts, kitchen sees it as en route but cannot confirm yet.
        Livewire::actingAs($this->kitchen)
            ->test(IncomingBoxes::class)
            ->assertSee('MB-REQ-FLOW', false)
            ->assertSee('On the way', false)
            ->assertSee('Waiting for rider handoff', false)
            ->call('receiveBox', $box->id)
            ->assertSet('errorMessage', 'Wait for the rider to hand this box before confirming receive.');

        Livewire::actingAs($this->rider)
            ->test(PendingBoxRuns::class)
            ->call('handWarehouseStock', $box->id)
            ->assertSet('errorMessage', null);

        $this->assertSame('handed_to_kitchen_stock', MiddoBoxLog::query()->where('middo_box_id', $box->id)->latest('id')->value('log_action'));

        $this->assertTrue(StaffAlert::query()
            ->where('user_id', $this->kitchen->id)
            ->where('type', StaffAlert::TYPE_OPS_TO_KITCHEN_BOX)
            ->where('meta->phase', 'handed')
            ->exists());

        Livewire::actingAs($this->kitchen)
            ->test(IncomingBoxes::class)
            ->assertSee('MB-REQ-FLOW', false)
            ->assertSee('Ready to receive', false)
            ->assertSee('Confirm receive', false)
            ->call('receiveBox', $box->id)
            ->assertSet('errorMessage', null);

        $box->refresh();
        $this->assertTrue($box->isAtKitchen($this->kitchen->id));
        $this->assertSame(
            KitchenBoxRequestBox::STATUS_RECEIVED,
            KitchenBoxRequestBox::query()->where('middo_box_id', $box->id)->value('status')
        );

        $events = KitchenBoxRequestLog::query()
            ->where('kitchen_box_request_id', $request->id)
            ->orderBy('id')
            ->pluck('event')
            ->all();

        $this->assertSame([
            KitchenBoxRequestLog::EVENT_REQUESTED,
            KitchenBoxRequestLog::EVENT_STAGED_FOR_PICKUP,
            KitchenBoxRequestLog::EVENT_RIDER_ACCEPTED,
            KitchenBoxRequestLog::EVENT_HANDED_TO_KITCHEN,
            KitchenBoxRequestLog::EVENT_RECEIVED_AT_KITCHEN,
        ], $events);
    }
}
