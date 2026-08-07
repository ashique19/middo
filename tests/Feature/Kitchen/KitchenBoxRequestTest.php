<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\BoxesAtKitchen;
use App\Livewire\Operation\MiddoBoxes;
use App\Models\KitchenBoxRequest;
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

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $opsRole = Role::create(['name' => 'operation']);
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
}
