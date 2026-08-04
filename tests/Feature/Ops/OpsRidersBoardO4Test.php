<?php

namespace Tests\Feature\Ops;

use App\Livewire\Delivery\CustomRuns as DeliveryCustomRuns;
use App\Livewire\Operation\CustomRuns as OpsCustomRuns;
use App\Livewire\Operation\RidersBoard;
use App\Models\CustomRun;
use App\Models\MenuItem;
use App\Models\MiddoOperatingCost;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\OpsDashboardMetrics;
use App\Support\OpsRiderBoard;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsRidersBoardO4Test extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $rider;

    protected User $kitchen;

    protected User $corporate;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'O4', 'mobile' => '01970000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'O4', 'mobile' => '01970000002',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active', 'balance' => 75,
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'O4', 'mobile' => '01970000003',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'O4', 'mobile' => '01970000004',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'O4 Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);
    }

    public function test_riders_board_lists_roster_and_awaiting_packed(): void
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'packed',
            'payment_status' => 'pending',
            'dispatched_at' => now(),
            'delivery_rider_id' => null,
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-O4',
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $riders = OpsRiderBoard::riders();
        $this->assertCount(1, $riders);
        $this->assertSame(75, $riders[0]['due_float']);

        $awaiting = OpsRiderBoard::awaitingAccept();
        $this->assertCount(1, $awaiting);
        $this->assertSame($order->id, $awaiting[0]['id']);

        $metrics = OpsDashboardMetrics::forRole('operation');
        $this->assertContains(
            'Packed awaiting rider',
            collect($metrics['attention'])->pluck('label')->all()
        );

        Livewire::actingAs($this->ops)
            ->test(RidersBoard::class)
            ->assertSee('Rider ops')
            ->assertSee('Rider O4')
            ->set('tab', 'awaiting')
            ->assertSee('#'.$order->id);

        $this->actingAs($this->ops)->get(route('operation.riders.index'))->assertOk();
    }

    public function test_force_cancel_started_custom_run_voids_commission(): void
    {
        $run = CustomRun::create([
            'from_label' => 'Warehouse',
            'to_label' => 'Kitchen',
            'commission_amount' => 30,
            'status' => CustomRun::STATUS_PENDING,
            'created_by' => $this->ops->id,
        ]);

        Livewire::actingAs($this->rider)
            ->test(DeliveryCustomRuns::class)
            ->call('startRun', $run->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(CustomRun::STATUS_STARTED, $run->fresh()->status);
        $this->assertSame(30, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(1, MiddoOperatingCost::query()
            ->where('reference_type', CustomRun::class)
            ->where('reference_id', $run->id)
            ->count());

        Livewire::actingAs($this->ops)
            ->test(RidersBoard::class)
            ->set('tab', 'custom')
            ->call('cancelCustomRun', $run->id)
            ->assertSet('errorMessage', '');

        $this->assertSame(CustomRun::STATUS_CANCELLED, $run->fresh()->status);
        $this->assertSame(0, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(0, MiddoOperatingCost::query()
            ->where('reference_type', CustomRun::class)
            ->where('reference_id', $run->id)
            ->count());
    }

    public function test_reassign_pending_custom_run(): void
    {
        $other = User::create([
            'first_name' => 'Other', 'last_name' => 'Rider', 'mobile' => '01970000009',
            'password' => 'password',
            'role_id' => Role::query()->where('name', 'delivery')->value('id'),
            'status' => 'active',
        ]);

        $run = CustomRun::create([
            'from_label' => 'A',
            'to_label' => 'B',
            'commission_amount' => 20,
            'status' => CustomRun::STATUS_PENDING,
            'rider_user_id' => $this->rider->id,
            'created_by' => $this->ops->id,
        ]);

        Livewire::actingAs($this->ops)
            ->test(OpsCustomRuns::class)
            ->call('reassignRun', $run->id, $other->id)
            ->assertSet('errorMessage', '');

        $this->assertSame($other->id, (int) $run->fresh()->rider_user_id);

        Livewire::actingAs($this->ops)
            ->test(RidersBoard::class)
            ->call('openReassign', $run->id)
            ->set('reassignRiderId', null)
            ->call('confirmReassign')
            ->assertSet('errorMessage', '');

        $this->assertNull($run->fresh()->rider_user_id);
    }
}
