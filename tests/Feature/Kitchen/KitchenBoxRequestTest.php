<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Livewire\Operation\MiddoBoxes;
use App\Models\KitchenBoxRequest;
use App\Models\MiddoBox;
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
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'note' => 'Need stock for lunch',
            'requested_by' => $this->kitchen->id,
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

    public function test_ops_can_mark_box_request_fulfilled(): void
    {
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 3,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('markBoxRequestFulfilled', $request->id)
            ->assertSet('errorMessage', null)
            ->assertDontSee('Kitchen box requests', false);

        $this->assertSame(KitchenBoxRequest::STATUS_FULFILLED, $request->fresh()->status);
        $this->assertSame($this->ops->id, (int) $request->fresh()->reviewed_by);
        $this->assertNotNull($request->fresh()->reviewed_at);
    }

    public function test_kitchen_can_cancel_own_pending_request(): void
    {
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 2,
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

    public function test_ops_send_partially_reduces_pending_request_quantity(): void
    {
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 3,
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
        $this->assertSame(KitchenBoxRequest::STATUS_PENDING, $request->status);
        $this->assertSame(2, $request->quantity);
        $this->assertSame($this->rider->id, (int) $box->fresh()->held_by_user_id);
    }
}
