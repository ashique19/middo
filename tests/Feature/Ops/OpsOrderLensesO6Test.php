<?php

namespace Tests\Feature\Ops;

use App\Livewire\Shared\OrderShow;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderLens;
use App\Support\OrderTransition;
use App\Support\StaffOrderRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsOrderLensesO6Test extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $corporate;

    protected User $kitchen;

    protected User $rider;

    protected User $otherKitchen;

    protected MenuItem $menu;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'O6', 'mobile' => '01990000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'O6', 'mobile' => '01990000002',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
            'balance' => 1000, 'company_name' => 'O6 Corp',
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'O6', 'mobile' => '01990000003',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
            'kitchen_tier' => 'gold',
        ]);
        $this->otherKitchen = User::create([
            'first_name' => 'Other', 'last_name' => 'Kitchen', 'mobile' => '01990000005',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
            'kitchen_tier' => 'gold',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'O6', 'mobile' => '01990000004',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'O6 Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);

        $this->order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'amount_paid' => 0,
            'address' => 'Lens Street',
            'order_status' => 'processing',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-O6',
            'menu_id' => $this->menu->id,
            'delivery_date' => $this->order->delivery_date,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($this->order->id);
    }

    public function test_ops_can_switch_lenses_and_corporate_hides_middo_rest(): void
    {
        Livewire::actingAs($this->ops)
            ->test(OrderShow::class, ['order' => $this->order])
            ->assertSet('lens', OrderLens::MIDDO)
            ->assertSee('Money flow')
            ->assertSee('Middo rest')
            ->call('switchLens', OrderLens::CORPORATE)
            ->assertSet('lens', OrderLens::CORPORATE)
            ->assertSee('Buyer view')
            ->assertDontSee('Middo rest')
            ->assertDontSee('Money flow')
            ->call('switchLens', OrderLens::KITCHEN)
            ->assertSee('Cook / dispatch')
            ->assertSee('Kitchen share')
            ->assertDontSee('Money flow')
            ->call('switchLens', OrderLens::RIDER)
            ->assertSee('Run sheet')
            ->assertSee('COD / commission')
            ->assertDontSee('Money flow');
    }

    public function test_ops_mark_ready_via_kitchen_lens_writes_audit(): void
    {
        Livewire::actingAs($this->ops)
            ->test(OrderShow::class, ['order' => $this->order, 'lens' => OrderLens::KITCHEN])
            ->assertSee('Mark ready')
            ->call('markReady')
            ->assertSet('forceError', null);

        $this->assertSame(OrderTransition::READY, $this->order->fresh()->order_status);
        $this->assertDatabaseHas('order_logs', [
            'order_id' => $this->order->id,
            'event' => 'ops_intervene',
            'performed_by' => $this->ops->id,
        ]);
    }

    public function test_kitchen_native_route_fixed_to_kitchen_lens(): void
    {
        $this->actingAs($this->kitchen)
            ->get(route('kitchen.orders.show', $this->order))
            ->assertOk()
            ->assertSee('Cook / dispatch')
            ->assertDontSee('Money flow')
            ->assertDontSee('Viewing as');

        Livewire::actingAs($this->kitchen)
            ->test(OrderShow::class, ['order' => $this->order])
            ->assertSet('lens', OrderLens::KITCHEN)
            ->call('switchLens', OrderLens::MIDDO)
            ->assertSet('lens', OrderLens::KITCHEN);
    }

    public function test_other_kitchen_cannot_open_order(): void
    {
        $this->actingAs($this->otherKitchen)
            ->get(route('kitchen.orders.show', $this->order))
            ->assertForbidden();
    }

    public function test_rider_native_route_for_assigned_order(): void
    {
        $this->order->update([
            'order_status' => OrderTransition::ON_THE_WAY_TO_DELIVERY,
            'delivery_rider_id' => $this->rider->id,
            'dispatched_at' => now(),
        ]);

        $this->actingAs($this->rider)
            ->get(route('delivery.orders.show', $this->order))
            ->assertOk()
            ->assertSee('Run sheet')
            ->assertSee('COD / commission')
            ->assertDontSee('Money flow')
            ->assertDontSee('Viewing as');
    }

    public function test_staff_order_routes_append_lens_query(): void
    {
        $this->actingAs($this->ops);

        $url = StaffOrderRoutes::show($this->order, 'kitchen');
        $this->assertStringContainsString('lens=kitchen', $url);
        $this->assertStringContainsString('/orders/'.$this->order->id, $url);
    }

    public function test_corporate_lens_payload_matches_presenter_parity(): void
    {
        $payload = OrderLens::payload($this->order->fresh(), OrderLens::CORPORATE, $this->ops);

        $this->assertSame(OrderLens::CORPORATE, $payload['lens']);
        $this->assertArrayHasKey('can_delete', $payload['buyer']);
        $this->assertArrayHasKey('amount_due', $payload['money']);
        $this->assertArrayNotHasKey('middo_rest', $payload['money'] ?? []);
        $this->assertArrayNotHasKey('summary', $payload['money'] ?? []);
    }
}
