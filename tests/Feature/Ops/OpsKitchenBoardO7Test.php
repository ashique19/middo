<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\Complaints;
use App\Livewire\Operation\ComplaintShow;
use App\Livewire\Operation\Kitchens;
use App\Livewire\Operation\MiddoBoxes;
use App\Livewire\Operation\SlaBoard;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\OpsBoxCustody;
use App\Support\OpsDashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsKitchenBoardO7Test extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $kitchen;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'delivery']);

        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Banani', 'city_id' => $city->id]);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'O7', 'mobile' => '01911000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'O7', 'mobile' => '01911000002',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
            'kitchen_tier' => 'gold', 'area_id' => $area->id,
        ]);
        $this->menu = MenuItem::create([
            'name' => 'O7 Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);

        User::create([
            'first_name' => 'Corp', 'last_name' => 'O7', 'mobile' => '01911000003',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
        ]);
    }

    public function test_kitchens_list_shows_tier_capacity_and_area(): void
    {
        Livewire::actingAs($this->ops)
            ->test(Kitchens::class)
            ->assertSee('Kitchen O7')
            ->assertSee('Gold')
            ->assertSee('Banani')
            ->assertSee('slot(s) left');
    }

    public function test_sla_board_bulk_assign_and_area_column(): void
    {
        $corporate = User::query()->where('mobile', '01911000003')->first();
        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'area_id' => $this->kitchen->area_id,
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-O7-POOL',
            'menu_id' => $this->menu->id,
            'delivery_date' => $order->delivery_date,
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);

        Livewire::actingAs($this->ops)
            ->test(SlaBoard::class)
            ->assertSee('GRP-O7-POOL')
            ->assertSee('Banani')
            ->assertSee('Bulk assign')
            ->call('toggleGroup', $group->id)
            ->set('bulkKitchenId', $this->kitchen->id)
            ->call('bulkAssignKitchen')
            ->assertSee('Assigned 1');

        $this->assertSame($this->kitchen->id, $group->fresh()->kitchen_id);
        $this->assertSame('processing', $order->fresh()->order_status);
    }

    public function test_box_custody_summary_and_ack_return(): void
    {
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-O7-RET',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'kitchen_id' => null,
            'held_by_user_id' => null,
            'total_uses_count' => 2,
        ]);
        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'warehouse',
            'log_action' => 'returned_to_warehouse',
            'notes' => 'Kitchen return',
            'performed_by' => $this->kitchen->id,
        ]);

        $summary = OpsBoxCustody::summary();
        $this->assertSame(1, $summary['returns']);
        $this->assertGreaterThanOrEqual(1, $summary['warehouse']);

        Livewire::actingAs($this->ops)
            ->test(MiddoBoxes::class)
            ->assertSee('Inbound returns')
            ->set('custodyFilter', 'returns')
            ->assertSee('MB-O7-RET')
            ->call('ackReturn', $box->id)
            ->assertSee('Confirmed receive');

        $this->assertDatabaseHas('middo_box_logs', [
            'middo_box_id' => $box->id,
            'log_action' => 'ops_acked_warehouse_return',
        ]);
        $this->assertSame(0, OpsBoxCustody::summary()['returns']);
    }

    public function test_complaints_inbox_and_dashboard_deep_link(): void
    {
        $corporate = User::query()->where('mobile', '01911000003')->first();
        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
        ]);
        $complaint = OrderComplaint::create([
            'order_id' => $order->id,
            'parent_id' => null,
            'is_reply' => false,
            'category' => 'food_quality',
            'message' => 'Meal arrived cold',
            'created_by' => $corporate->id,
        ]);

        $metrics = OpsDashboardMetrics::forRole('operation');
        $attention = collect($metrics['attention'])->firstWhere('label', 'Open complaints');
        $this->assertNotNull($attention);
        $this->assertSame('operation.complaints.index', $attention['route']);

        $this->actingAs($this->ops)
            ->get(route('operation.complaints.index'))
            ->assertOk()
            ->assertSee('Meal arrived cold');

        Livewire::actingAs($this->ops)
            ->test(ComplaintShow::class, ['complaint' => $complaint])
            ->set('replyMessage', 'Sorry — we will follow up with the kitchen.')
            ->call('reply')
            ->assertSet('statusMessage', 'Reply posted.');

        $this->assertDatabaseHas('order_complaints', [
            'parent_id' => $complaint->id,
            'is_reply' => true,
            'created_by' => $this->ops->id,
        ]);
    }
}
