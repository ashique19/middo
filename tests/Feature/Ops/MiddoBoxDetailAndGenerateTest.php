<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\GenerateMiddoBoxesModal;
use App\Livewire\Operation\MiddoBoxes;
use App\Livewire\Operation\MiddoBoxShow;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Role;
use App\Models\User;
use App\Support\MiddoBoxLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MiddoBoxDetailAndGenerateTest extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'Box', 'mobile' => '01994000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
    }

    public function test_list_orders_latest_first(): void
    {
        $older = MiddoBox::create([
            'qr_code_id' => 'MB-000001',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);
        $newer = MiddoBox::create([
            'qr_code_id' => 'MB-000002',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->assertSeeInOrder(['MB-000002', 'MB-000001']);

        $this->assertTrue($newer->id > $older->id);
    }

    public function test_list_shows_held_by_name_and_phone(): void
    {
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $holder = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01718001122',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        MiddoBox::create([
            'qr_code_id' => 'MB-HELD-1',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'held_by_user_id' => $holder->id,
            'kitchen_id' => $holder->id,
            'total_uses_count' => 1,
        ]);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->assertSee('Gulshan Kitchen', false)
            ->assertSee('01718001122', false);
    }

    public function test_generate_modal_lists_new_ids(): void
    {
        Livewire::actingAs($this->ops)
            ->test(GenerateMiddoBoxesModal::class)
            ->call('openModal')
            ->set('quantity', 2)
            ->call('generate')
            ->assertSet('showModal', true)
            ->assertCount('generatedBoxes', 2)
            ->assertSee('MB-000001')
            ->assertSee('MB-000002')
            ->assertSee('Generated 2');

        $this->assertSame(2, MiddoBox::query()->count());
    }

    public function test_box_detail_shows_lifecycle_and_tracking_tree(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-000010',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 3,
            'unit_cost_bdt' => 900,
        ]);
        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'warehouse',
            'log_action' => 'registered_at_warehouse',
        ]);
        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'in_transit',
            'log_action' => 'picked_by_delivery_from_kitchen',
        ]);
        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'damaged',
            'log_action' => 'marked_damaged_at_kitchen',
        ]);

        $metrics = MiddoBoxLifecycle::metrics($box->fresh());
        $this->assertSame(3, $metrics['run_count']);
        $this->assertSame(900, $metrics['unit_cost']);
        $this->assertSame(300.0, $metrics['cost_per_run']);
        $this->assertNotNull($metrics['damaged_at']);

        $tree = MiddoBoxLifecycle::trackingTree($box->fresh());
        $this->assertSame('marked_damaged_at_kitchen', $tree[0]['action']);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxShow::class, ['middoBox' => $box])
            ->assertSee('MB-000010')
            ->assertSee('Tracking tree')
            ->assertSee('Marked Damaged At Kitchen')
            ->assertSee('Cost / run')
            ->set('unitCostBdt', 1200)
            ->call('saveUnitCost')
            ->assertSet('statusMessage', 'Unit cost saved.');

        $this->assertSame(1200, (int) $box->fresh()->unit_cost_bdt);

        $this->actingAs($this->ops)
            ->get(route('operation.middo-boxes.show', $box))
            ->assertOk()
            ->assertSee('MB-000010');
    }

    public function test_retire_writes_lifecycle_log(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-000020',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->call('retire', $box->id);

        $this->assertSame('retired', $box->fresh()->asset_status);
        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'retired_at_warehouse',
        ]);
        $this->assertNotNull($box->fresh()->retiredAt());
    }
}
