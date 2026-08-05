<?php

namespace Tests\Feature\Ops;

use App\Livewire\Operation\OpsDayBoard;
use App\Models\CashHandover;
use App\Models\CashHandoverOrder;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\OpsDayChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsDayChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $rider;

    protected User $corporate;

    protected MenuItem $menu;

    protected string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'Day', 'mobile' => '01995000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'Day', 'mobile' => '01995000002',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'Day', 'mobile' => '01995000003',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'Day Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);
        $this->date = now('Asia/Dhaka')->toDateString();
    }

    public function test_ops_day_sections_cover_a_through_h(): void
    {
        Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $this->date,
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'packed',
            'payment_status' => 'pending',
            'dispatched_at' => now(),
            'delivery_rider_id' => null,
        ]);

        Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $this->date,
            'delivery_time' => '1:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'delivered',
            'payment_status' => 'pending',
            'delivery_rider_id' => $this->rider->id,
            'cash_collected' => 0,
        ]);

        $short = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $this->date,
            'delivery_time' => '2:00 PM',
            'total_amount' => 200,
            'amount_paid' => 100,
            'address' => 'HQ',
            'order_status' => 'delivered',
            'payment_status' => 'pending',
            'delivery_rider_id' => $this->rider->id,
            'cash_collected' => 100,
            'cash_due_to_middo' => 60,
        ]);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-DAY-001',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'ready_for_pickup' => true,
            'ready_for_pickup_at' => now('Asia/Dhaka'),
            'total_uses_count' => 1,
        ]);
        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'warehouse',
            'log_action' => 'dispatched_to_kitchen',
            'created_at' => now('Asia/Dhaka'),
        ]);

        $handover = CashHandover::create([
            'rider_id' => $this->rider->id,
            'amount' => 60,
            'status' => CashHandover::STATUS_PENDING,
            'target' => CashHandover::TARGET_MIDDO,
        ]);
        CashHandoverOrder::create([
            'cash_handover_id' => $handover->id,
            'order_id' => $short->id,
            'amount' => 60,
        ]);

        $report = OpsDayChecklist::forDate($this->date);
        $ids = collect($report['sections'])->pluck('id')->all();
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], $ids);

        $byId = collect($report['sections'])->keyBy('id');
        $this->assertGreaterThanOrEqual(1, $byId['a']['counts']['awaiting']);
        $this->assertGreaterThanOrEqual(1, $byId['b']['counts']['delivered']);
        $this->assertGreaterThanOrEqual(1, $byId['c']['counts']['ready']);
        $this->assertGreaterThanOrEqual(1, $byId['f']['counts']['dispatched']);
        $this->assertGreaterThanOrEqual(1, $byId['g']['counts']['unpaid']);
        $this->assertGreaterThanOrEqual(1, $byId['g']['counts']['short']);
        $this->assertGreaterThanOrEqual(1, $byId['h']['counts']['middo_pending']);
        $this->assertGreaterThan(0, $report['totals']['attention']);

        Livewire::actingAs($this->ops)
            ->test(OpsDayBoard::class)
            ->assertSee('Ops day checklist')
            ->assertSee('Kitchen → Delivery')
            ->assertSee('COD: Customer → Delivery')
            ->assertSee('Cash: Delivery → Kitchen / Middo')
            ->set('deliveryDate', $this->date)
            ->assertSee('Awaiting accept');

        $this->actingAs($this->ops)
            ->get(route('operation.ops-day.index'))
            ->assertOk();
    }
}
