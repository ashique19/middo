<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\CashHandovers as DeliveryCashHandovers;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Delivery\PaymentModal;
use App\Livewire\Kitchen\CashHandovers as KitchenCashHandovers;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Operation\CashHandovers as OpsCashHandovers;
use App\Models\CashHandover;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\MiddoCashLedger;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RiderCashDueR3Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $rider;

    protected User $customer;

    protected User $ops;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $opsRole = Role::create(['name' => 'operation']);

        $this->kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'R3',
            'mobile' => '01930000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'R3',
            'mobile' => '01930000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'R3',
            'mobile' => '01930000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $this->ops = User::create([
            'first_name' => 'Ops',
            'last_name' => 'R3',
            'mobile' => '01930000004',
            'password' => 'password',
            'role_id' => $opsRole->id,
            'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'R3 Lunch',
            'price' => 200,
            'kitchen_commission' => 50,
            'delivery_commission' => 40,
        ]);
    }

    protected function deliverOrder(): Order
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
            'name' => 'GRP-R3-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-R3-'.uniqid(),
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

        return $order->fresh();
    }

    public function test_cash_collect_nets_commission_into_due(): void
    {
        $order = $this->deliverOrder();
        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->assertSet('commissionAmount', 40)
            ->assertSet('dueToMiddo', 160)
            ->call('selectCash')
            ->call('confirmCashPayment')
            ->assertSet('showModal', false);

        $order->refresh();
        $this->assertSame(200, (int) $order->cash_collected);
        $this->assertSame(160, (int) $order->cash_due_to_middo);
        $this->assertSame(160, (int) $this->rider->fresh()->balance);
        $this->assertSame(0, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(
            PartnerPayable::STATUS_SETTLED,
            PartnerPayable::query()
                ->where('order_id', $order->id)
                ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
                ->value('status')
        );
    }

    public function test_kitchen_handover_uses_due_only(): void
    {
        $order = $this->deliverOrder();

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectCash')
            ->call('confirmCashPayment');

        Livewire::actingAs($this->rider)
            ->test(DeliveryCashHandovers::class)
            ->set('target', CashHandover::TARGET_KITCHEN)
            ->call('toggleOrder', $order->id)
            ->call('createHandover')
            ->assertSet('errorMessage', null);

        $handover = CashHandover::query()->first();
        $this->assertNotNull($handover);
        $this->assertSame(160, (int) $handover->amount);
        $this->assertTrue($handover->isKitchenTarget());

        Livewire::actingAs($this->kitchen)
            ->test(KitchenCashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(0, (int) $this->rider->fresh()->balance);
        // Kitchen already holds +50 share from dispatch; Due debit nets to -110.
        $this->assertSame(-110, KitchenAccountLedger::balance($this->kitchen->id));
        $this->assertSame(0, MiddoCashLedger::balance());
    }

    public function test_middo_handover_credits_middo_cash(): void
    {
        $order = $this->deliverOrder();

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectCash')
            ->call('confirmCashPayment');

        Livewire::actingAs($this->rider)
            ->test(DeliveryCashHandovers::class)
            ->set('target', CashHandover::TARGET_MIDDO)
            ->call('toggleOrder', $order->id)
            ->call('createHandover')
            ->assertSet('errorMessage', null);

        $handover = CashHandover::query()->first();
        $this->assertSame(CashHandover::TARGET_MIDDO, $handover->target);

        Livewire::actingAs($this->ops)
            ->test(OpsCashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(0, (int) $this->rider->fresh()->balance);
        $this->assertSame(160, MiddoCashLedger::balance());
        // Middo Due path does not touch kitchen wallet (share remains).
        $this->assertSame(50, KitchenAccountLedger::balance($this->kitchen->id));
    }
}
