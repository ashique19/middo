<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Livewire\Operation\MiddoBoxes;
use App\Models\KitchenBoxRequest;
use App\Models\KitchenBoxRequestBox;
use App\Models\MiddoBox;
use App\Models\Role;
use App\Models\User;
use App\Support\OpsBoxCustody;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsStageBoxSelectFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $kitchen;

    protected User $rider;

    protected function setUp(): void
    {
        parent::setUp();
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $opsRole = Role::create(['name' => 'operation']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        Role::create(['name' => 'admin']);

        $this->kitchen = User::create([
            'first_name' => 'Kit',
            'last_name' => 'Chen',
            'mobile' => '01719000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $this->ops = User::create([
            'first_name' => 'Op',
            'last_name' => 'S',
            'mobile' => '01719000002',
            'password' => 'password',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Ri',
            'last_name' => 'Der',
            'mobile' => '01719000003',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
    }

    public function test_parent_ready_for_pickup_opens_modal_with_selected_ids(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-SEL-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('toggleBoxSelection', $box->id)
            ->assertSet('selectedBoxIds', [$box->id])
            ->call('openAssignModal')
            ->assertDispatched('open-assign-middo-boxes-modal', boxIds: [$box->id]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->dispatch('open-assign-middo-boxes-modal', boxIds: [$box->id])
            ->assertSet('showModal', true)
            ->assertSet('boxIds', [$box->id]);
    }

    public function test_staged_warehouse_box_is_not_selectable_again(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-SEL-2',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 2,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', ['boxIds' => [$box->id]])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('kitchen_box_request_boxes', [
            'middo_box_id' => $box->id,
            'status' => KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP,
        ]);

        $this->assertSame('at_middo_warehouse', $box->fresh()->asset_status);
        $this->assertFalse($box->fresh()->load('requestBox')->isAvailableForKitchenStaging());
        $this->assertTrue($box->fresh()->load('requestBox')->isStagedForKitchenPickup());

        $summary = OpsBoxCustody::summary();
        $this->assertSame(0, $summary['warehouse']);
        $this->assertSame(1, $summary['to_kitchen']);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->assertSee('MB-SEL-2', false)
            ->assertSee('Staged for pickup', false)
            ->assertDontSeeHtml('wire:click.prevent="toggleBoxSelection('.$box->id.')"')
            ->call('toggleBoxSelection', $box->id)
            ->assertSet('selectedBoxIds', [])
            ->assertSet('errorMessage', fn ($msg) => is_string($msg) && str_contains($msg, 'Only free warehouse boxes'));

        Livewire::actingAs($this->ops)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', ['boxIds' => [$box->id]])
            ->set('selectedRiderId', $this->rider->id)
            ->set('selectedKitchenId', $this->kitchen->id)
            ->call('save')
            ->assertHasErrors(['selectedKitchenId']);

        $this->assertSame(1, (int) $request->fresh()->allocated_qty);
    }
}
