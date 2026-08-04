<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\AssignKitchenModal;
use App\Livewire\Operation\SlaBoard;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\OpsDashboardMetrics;
use App\Support\OpsSlaBoard;
use App\Support\OrderTransition;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsSlaBoardO2Test extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $kitchen;

    protected User $corporate;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'admin']);

        $this->ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'O2',
            'mobile' => '01950000001',
            'password' => 'password',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'O2',
            'mobile' => '01950000002',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'kitchen_tier' => 'gold',
            'allowed_open_groups' => 5,
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'O2',
            'mobile' => '01950000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'O2 Thali',
            'price' => 200,
            'kitchen_commission' => 50,
            'delivery_commission' => 40,
        ]);
    }

    public function test_unassigned_closed_window_surfaces_on_board_and_dashboard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 14:00:00', 'Asia/Dhaka'));

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 2,
            'delivery_date' => '2026-08-05',
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-O2-CLOSED',
            'menu_id' => $this->menu->id,
            'delivery_date' => '2026-08-05',
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);

        $rows = OpsSlaBoard::unassignedGroups();
        $this->assertCount(1, $rows);
        $this->assertSame('closed', $rows[0]['accept_window']['state']);

        $counts = OpsSlaBoard::counts();
        $this->assertSame(1, $counts['unassigned_closed']);

        $metrics = OpsDashboardMetrics::forRole('operation');
        $this->assertContains(
            'Unassigned — accept window closed',
            collect($metrics['attention'])->pluck('label')->all()
        );
        $this->assertSame(
            'operation.sla.index',
            collect($metrics['attention'])->firstWhere('label', 'Unassigned — accept window closed')['route']
        );

        Livewire::actingAs($this->ops)
            ->test(SlaBoard::class)
            ->assertSee('GRP-O2-CLOSED')
            ->assertSee('Window closed');

        $this->actingAs($this->ops)->get(route('operation.sla.index'))->assertOk();

        Carbon::setTestNow();
    }

    public function test_late_to_pack_lists_assigned_orders_past_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 12:30:00', 'Asia/Dhaka'));
        config(['middo.dispatch_deadline_minutes_before' => 60]);

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => '2026-08-05',
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => OrderTransition::PROCESSING,
            'payment_status' => 'pending',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-O2-LATE',
            'menu_id' => $this->menu->id,
            'delivery_date' => '2026-08-05',
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $late = OpsSlaBoard::lateToPack();
        $this->assertCount(1, $late);
        $this->assertSame($order->id, $late[0]['id']);

        $metrics = OpsDashboardMetrics::forRole('operation');
        $this->assertContains(
            'Late to pack / past deadline',
            collect($metrics['attention'])->pluck('label')->all()
        );

        Livewire::actingAs($this->ops)
            ->test(SlaBoard::class)
            ->set('tab', 'late')
            ->assertSee('#'.$order->id);

        Carbon::setTestNow();
    }

    public function test_assign_modal_shows_accept_window_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 14:00:00', 'Asia/Dhaka'));

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => '2026-08-05',
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-O2-MODAL',
            'menu_id' => $this->menu->id,
            'delivery_date' => '2026-08-05',
            'kitchen_id' => null,
        ]);
        $group->orders()->attach($order->id);

        Livewire::actingAs($this->ops)
            ->test(AssignKitchenModal::class)
            ->call('openModal', $group->id)
            ->assertSet('showModal', true)
            ->assertSet('acceptWindow.state', 'closed')
            ->assertSee('Kitchen accept window')
            ->assertSee('Ops can still assign');

        Carbon::setTestNow();
    }
}
