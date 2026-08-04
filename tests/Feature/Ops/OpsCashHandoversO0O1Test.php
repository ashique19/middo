<?php

namespace Tests\Feature\Ops;

use App\Livewire\Delivery\CashHandovers as DeliveryCashHandovers;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Delivery\PaymentModal;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Operation\CashHandovers as OpsCashHandovers;
use App\Models\CashHandover;
use App\Models\CashHandoverOrder;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Nav;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\OpsDashboardMetrics;
use App\Support\OrderTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsCashHandoversO0O1Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $rider;

    protected User $customer;

    protected User $ops;

    protected User $admin;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $opsRole = Role::create(['name' => 'operation']);
        $adminRole = Role::create(['name' => 'admin']);

        $this->kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'O0',
            'mobile' => '01940000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'O0',
            'mobile' => '01940000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'O0',
            'mobile' => '01940000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $this->ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'O0',
            'mobile' => '01940000004',
            'password' => 'password',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);
        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'O0',
            'mobile' => '01940000005',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'O0 Thali',
            'price' => 200,
            'delivery_commission' => 40,
            'kitchen_commission' => 50,
        ]);
    }

    public function test_dashboard_middo_handover_attention_links_to_cash_handovers(): void
    {
        CashHandover::create([
            'rider_id' => $this->rider->id,
            'amount' => 100,
            'target' => CashHandover::TARGET_MIDDO,
            'status' => 'pending',
        ]);
        CashHandover::create([
            'rider_id' => $this->rider->id,
            'amount' => 50,
            'target' => CashHandover::TARGET_KITCHEN,
            'status' => 'pending',
        ]);

        $metrics = OpsDashboardMetrics::forRole('operation');
        $byLabel = collect($metrics['attention'])->keyBy('label');

        $this->assertSame(1, $metrics['money']['pending_middo_handovers']);
        $this->assertSame(100, $metrics['money']['pending_middo_handover_amount']);
        $this->assertSame(1, $metrics['money']['pending_kitchen_handovers']);

        $this->assertTrue($byLabel->has('Pending Middo Due handovers'));
        $this->assertSame('operation.cash-handovers', $byLabel['Pending Middo Due handovers']['route']);
        $this->assertTrue($byLabel->has('Pending kitchen cash handovers'));
        $this->assertNull($byLabel['Pending kitchen cash handovers']['route']);

        $routes = collect($metrics['quick_links'])->pluck('route')->all();
        $this->assertContains('operation.cash-handovers', $routes);
    }

    public function test_admin_and_ops_cash_handover_routes_render(): void
    {
        $this->actingAs($this->ops)->get(route('operation.cash-handovers'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.cash-handovers'))->assertOk();
    }

    public function test_nav_migration_seeds_cash_handover_links(): void
    {
        // Migration no-ops when roles are missing at migrate time; re-apply upsert now.
        $migration = require database_path('migrations/2026_08_05_010000_add_ops_cash_handover_navs.php');
        $migration->up();

        $this->assertTrue(
            Nav::query()->where('route_name', 'operation.cash-handovers')->exists()
        );
        $this->assertTrue(
            Nav::query()->where('route_name', 'admin.cash-handovers')->exists()
        );
    }

    public function test_ops_reject_frees_orders_for_resubmit(): void
    {
        $order = $this->deliverAndCollect();

        Livewire::actingAs($this->rider)
            ->test(DeliveryCashHandovers::class)
            ->set('target', CashHandover::TARGET_MIDDO)
            ->call('toggleOrder', $order->id)
            ->call('createHandover')
            ->assertSet('errorMessage', null);

        $handover = CashHandover::query()->first();
        $this->assertNotNull($handover);
        $this->assertSame(1, CashHandoverOrder::query()->count());

        Livewire::actingAs($this->ops)
            ->test(OpsCashHandovers::class)
            ->set('rejectReason', 'Amount mismatch')
            ->call('reject', $handover->id)
            ->assertSet('errorMessage', null);

        $handover->refresh();
        $this->assertSame('rejected', $handover->status);
        $this->assertStringContainsString('Amount mismatch', (string) $handover->notes);
        $this->assertSame(0, CashHandoverOrder::query()->count());
        $this->assertSame((int) $order->dueToMiddoAmount(), (int) $this->rider->fresh()->balance);

        Livewire::actingAs($this->rider)
            ->test(DeliveryCashHandovers::class)
            ->set('target', CashHandover::TARGET_MIDDO)
            ->call('toggleOrder', $order->id)
            ->call('createHandover')
            ->assertSet('errorMessage', null);

        $this->assertSame(2, CashHandover::query()->count());
        $this->assertSame(1, CashHandover::query()->where('status', 'pending')->count());
    }

    protected function deliverAndCollect(): Order
    {
        $today = now('Asia/Dhaka')->toDateString();
        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-O0-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-O0-'.uniqid(),
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id)
            ->call('toggleBox', $box->id)
            ->call('dispatchOrder')
            ->assertSet('showModal', false);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->call('deliverToConsumer', $order->id)
            ->assertSet('errorMessage', null);

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectCash')
            ->call('confirmCashPayment')
            ->assertSet('showModal', false);

        return $order->fresh();
    }
}
