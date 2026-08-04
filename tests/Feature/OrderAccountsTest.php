<?php

namespace Tests\Feature;

use App\Livewire\Kitchen\CashHandovers;
use App\Livewire\Shared\AccountsHub;
use App\Models\CashHandover;
use App\Models\CashHandoverOrder;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupOrder;
use App\Models\OrderMoneyEvent;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\MiddoCashLedger;
use App\Support\OrderMoneyFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name]);
    }

    private function user(string $roleName, array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => ucfirst($roleName),
            'last_name' => 'User',
            'mobile' => '01310'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $this->role($roleName)->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 0,
        ], $overrides));
    }

    public function test_order_create_writes_billing_and_payment_events(): void
    {
        $corporate = $this->user('corporate', ['balance' => 5000]);
        $menu = MenuItem::create([
            'name' => 'Thali',
            'price' => 200,
            'kitchen_commission' => 40,
            'delivery_commission' => 20,
            'meals_cost' => 80,
            'other_cost' => 10,
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'amount_paid' => 400,
            'prepaid_amount' => 400,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $order->refresh();
        $this->assertSame(400, (int) $order->food_amount);
        $this->assertSame(80, (int) $order->kitchen_share_amount); // snapshotted at create via breakdown
        $this->assertTrue(OrderMoneyEvent::query()->where('order_id', $order->id)->where('event_type', 'placed')->exists());
        $this->assertTrue(OrderMoneyEvent::query()->where('order_id', $order->id)->where('event_type', 'payment')->exists());

        $tree = OrderMoneyFlow::treeForOrder($order->fresh(['moneyEvents', 'partnerPayables', 'menuItem']));
        $this->assertSame(400, $tree['summary']['food']);
        $this->assertNotEmpty($tree['billing']);
        $this->assertNotEmpty($tree['movements']);
    }

    public function test_run_start_and_paid_accrue_partner_payables_once(): void
    {
        $corporate = $this->user('corporate');
        $kitchen = $this->user('kitchen');
        $rider = $this->user('delivery');
        $menu = MenuItem::create([
            'name' => 'Thali',
            'price' => 200,
            'kitchen_commission' => 50,
            'delivery_commission' => 25,
            'meals_cost' => 70,
            'other_cost' => 5,
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 2,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'amount_paid' => 0,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'Group',
            'delivery_date' => $order->delivery_date,
            'menu_id' => $menu->id,
            'kitchen_id' => $kitchen->id,
            'created_by' => $kitchen->id,
        ]);
        OrderGroupOrder::create([
            'order_group_id' => $group->id,
            'order_id' => $order->id,
        ]);

        // Kitchen share accrues on dispatch.
        $order->update([
            'dispatched_at' => now(),
            'order_status' => 'packed',
            'updated_by' => $kitchen->id,
        ]);
        $this->assertSame(100, KitchenAccountLedger::balance($kitchen->id));
        $this->assertDatabaseHas('partner_payables', [
            'order_id' => $order->id,
            'beneficiary_role' => 'kitchen',
            'amount' => 100,
            'status' => 'open',
        ]);

        // Delivery share accrues on run start (rider accept).
        $order->update([
            'order_status' => 'on_the_way_to_delivery',
            'delivery_rider_id' => $rider->id,
            'updated_by' => $rider->id,
        ]);
        \App\Support\OrderMoneyFlow::accrueDeliveryShareOnRunStart($order->fresh(['menuItem', 'orderGroup']), $rider);

        $this->assertDatabaseHas('partner_payables', [
            'order_id' => $order->id,
            'beneficiary_role' => 'delivery',
            'amount' => 50,
            'status' => 'open',
        ]);
        $this->assertSame(50, \App\Support\RiderAccountLedger::balance($rider->id));
        $this->assertSame(1, OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', 'delivery_share')
            ->count());

        $order->update([
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
            'amount_paid' => 400,
            'cash_collected' => 400,
        ]);

        // No second delivery_share; middo_rest recorded once.
        $this->assertSame(1, OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', 'delivery_share')
            ->count());
        $this->assertSame(50, \App\Support\RiderAccountLedger::balance($rider->id));
        $this->assertSame(100, KitchenAccountLedger::balance($kitchen->id));

        $order->refresh();
        $this->assertSame(250, (int) $order->middo_rest_amount); // 400 - 100 - 50
        $this->assertTrue(OrderMoneyEvent::query()->where('order_id', $order->id)->where('event_type', 'cash_collected')->exists());
        $this->assertTrue(OrderMoneyEvent::query()->where('order_id', $order->id)->where('event_type', 'middo_rest')->exists());
    }

    public function test_settle_payable_debits_middo_cash_and_records_event(): void
    {
        $admin = $this->user('admin');
        $corporate = $this->user('corporate');
        $kitchen = $this->user('kitchen');
        $menu = MenuItem::create([
            'name' => 'Thali',
            'price' => 200,
            'kitchen_commission' => 40,
            'delivery_commission' => 0,
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'amount_paid' => 200,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        // Seed Middo cash so settlement can debit.
        MiddoCashLedger::credit(500, 'seed', null, null, 'Seed', $admin->id);

        $payable = PartnerPayable::create([
            'order_id' => $order->id,
            'beneficiary_user_id' => $kitchen->id,
            'beneficiary_role' => PartnerPayable::ROLE_KITCHEN,
            'amount' => 40,
            'status' => PartnerPayable::STATUS_OPEN,
        ]);

        KitchenAccountLedger::credit(
            $kitchen->id,
            40,
            'share_accrued',
            PartnerPayable::class,
            $payable->id,
            'Test seed',
            $admin->id
        );

        OrderMoneyFlow::settlePayable($payable, $admin->id);

        $this->assertSame(460, MiddoCashLedger::balance());
        $this->assertSame(0, KitchenAccountLedger::balance($kitchen->id));
        $this->assertSame(PartnerPayable::STATUS_SETTLED, $payable->fresh()->status);
        $this->assertTrue(OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', 'payable_settled')
            ->exists());
    }

    public function test_cash_handover_debits_kitchen_wallet(): void
    {
        $kitchen = $this->user('kitchen');
        $rider = $this->user('delivery', ['balance' => 300]);
        $corporate = $this->user('corporate');
        $menu = MenuItem::create(['name' => 'Thali', 'price' => 200]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'amount_paid' => 200,
            'cash_collected' => 200,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
            'payment_method' => 'cash_on_delivery',
            'delivery_rider_id' => $rider->id,
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-CASH-1',
            'menu_id' => $menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $handover = CashHandover::create([
            'rider_id' => $rider->id,
            'amount' => 200,
            'status' => 'pending',
        ]);
        CashHandoverOrder::create([
            'cash_handover_id' => $handover->id,
            'order_id' => $order->id,
            'amount' => 200,
        ]);

        Livewire::actingAs($kitchen)
            ->test(CashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(0, MiddoCashLedger::balance());
        $this->assertSame(-200, KitchenAccountLedger::balance($kitchen->id));
        $event = OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', 'cash_to_kitchen')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame(200, (int) $event->amount);
    }

    public function test_admin_accounts_hub_loads(): void
    {
        $admin = $this->user('admin');

        Livewire::actingAs($admin)
            ->test(AccountsHub::class)
            ->assertStatus(200)
            ->assertSee('Accounts')
            ->assertSee('Middo cash on hand');
    }
}
