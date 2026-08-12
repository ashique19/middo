<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Livewire\Operation\MiddoBoxes;
use App\Models\KitchenBoxRequest;
use App\Models\KitchenBoxRequestBox;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Role;
use App\Models\User;
use App\Support\MiddoBoxLifecycle;
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

    public function test_tracking_tree_adds_rider_to_legacy_staged_notes(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-SEL-LEGACY',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 1,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);
        KitchenBoxRequestBox::create([
            'kitchen_box_request_id' => $request->id,
            'middo_box_id' => $box->id,
            'rider_id' => $this->rider->id,
            'status' => KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP,
        ]);
        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'warehouse',
            'log_action' => 'staged_for_kitchen_pickup',
            'notes' => 'Ready for rider pickup → Kit Chen',
            'performed_by' => $this->ops->id,
        ]);

        $tree = MiddoBoxLifecycle::trackingTree($box->fresh());
        $row = $tree->firstWhere('action', 'staged_for_kitchen_pickup');
        $this->assertNotNull($row);
        $this->assertStringContainsString($this->rider->name, (string) $row['notes']);
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

        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'staged_for_kitchen_pickup',
            'notes' => 'Ready for rider pickup by '.$this->rider->name.' → '.$this->kitchen->name,
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
            ->assertDontSeeHtml('wire:click="toggleBoxSelection('.$box->id.')"')
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

    public function test_warehouse_tile_filter_excludes_staged_boxes(): void
    {
        $free = MiddoBox::create([
            'qr_code_id' => 'MB-FREE-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $staged = MiddoBox::create([
            'qr_code_id' => 'MB-STAGED-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 1,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);
        KitchenBoxRequestBox::create([
            'kitchen_box_request_id' => $request->id,
            'middo_box_id' => $staged->id,
            'rider_id' => $this->rider->id,
            'status' => KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP,
        ]);

        $summary = OpsBoxCustody::summary();
        $this->assertSame(1, $summary['warehouse']);
        $this->assertSame(1, $summary['to_kitchen']);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('toggleCustodyFilter', 'warehouse')
            ->assertSet('custodyFilter', 'warehouse')
            ->assertSee('MB-FREE-1', false)
            ->assertDontSee('MB-STAGED-1', false)
            ->assertSee('free warehouse stock', false);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('toggleCustodyFilter', 'to_kitchen')
            ->assertSet('custodyFilter', 'to_kitchen')
            ->assertSee('MB-STAGED-1', false)
            ->assertDontSee('MB-FREE-1', false)
            ->assertSee('Staged for pickup', false);

        $this->assertSame($free->id, OpsBoxCustody::warehouseFreeQuery()->value('id'));
        $this->assertSame($staged->id, OpsBoxCustody::toKitchenQuery()->value('id'));
    }

    public function test_header_checkbox_selects_latest_ten_unassigned_boxes(): void
    {
        $created = [];
        for ($i = 1; $i <= 12; $i++) {
            $created[] = MiddoBox::create([
                'qr_code_id' => 'MB-BATCH-'.$i,
                'box_model_type' => 'standard_insulated',
                'asset_status' => 'at_middo_warehouse',
                'total_uses_count' => 0,
            ]);
        }

        // Staged / non-warehouse boxes must be skipped.
        $staged = MiddoBox::create([
            'qr_code_id' => 'MB-BATCH-STAGED',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $request = KitchenBoxRequest::create([
            'kitchen_id' => $this->kitchen->id,
            'quantity' => 1,
            'allocated_qty' => 0,
            'status' => KitchenBoxRequest::STATUS_PENDING,
            'requested_by' => $this->kitchen->id,
        ]);
        KitchenBoxRequestBox::create([
            'kitchen_box_request_id' => $request->id,
            'middo_box_id' => $staged->id,
            'rider_id' => $this->rider->id,
            'status' => KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP,
        ]);
        MiddoBox::create([
            'qr_code_id' => 'MB-BATCH-ACTIVE',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'total_uses_count' => 0,
        ]);

        $expectedIds = collect($created)->sortByDesc('id')->take(10)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $component = Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('toggleSelectLatestUnassigned')
            ->assertSet('selectedBoxIds', $expectedIds)
            ->assertSeeHtml('aria-label="Select latest 10 unassigned boxes"');

        $this->assertNotContains($staged->id, $component->get('selectedBoxIds'));

        $component
            ->call('toggleSelectLatestUnassigned')
            ->assertSet('selectedBoxIds', []);
    }

    public function test_header_checkbox_selects_fewer_when_under_ten_available(): void
    {
        $a = MiddoBox::create([
            'qr_code_id' => 'MB-FEW-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $b = MiddoBox::create([
            'qr_code_id' => 'MB-FEW-2',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('toggleSelectLatestUnassigned')
            ->assertSet('selectedBoxIds', [$b->id, $a->id]);
    }
}
