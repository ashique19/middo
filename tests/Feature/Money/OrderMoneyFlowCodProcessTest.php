<?php

namespace Tests\Feature\Money;

use App\Livewire\Delivery\CashHandovers as DeliveryCashHandovers;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Delivery\PaymentModal;
use App\Livewire\Kitchen\CashHandovers as KitchenCashHandovers;
use App\Livewire\Kitchen\ActiveOrders;
use App\Livewire\Operation\CashHandovers as OpsCashHandovers;
use App\Models\CashHandover;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderMoneyEvent;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\MiddoCashLedger;
use App\Support\OrderMoneyFlow;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\KitchenBoxFactory;
use Tests\Support\LunchRunFlow;
use Tests\Support\MoneyFlowAssertions;
use Tests\TestCase;

/**
 * COD process-flow E2E: corporate → kitchen → ops → rider → cash → handover,
 * asserting money math and role balances after every step.
 */
class OrderMoneyFlowCodProcessTest extends TestCase
{
    use RefreshDatabase;

    private User $corporate;

    private User $kitchen;

    private User $rider;

    private User $ops;

    private User $accounts;

    private MenuItem $menu;

    /** @var array{food: int, vat: int, food_ex_vat: int, kitchen: int, delivery: int, middo_rest: int, bill_net: int} */
    private array $oracle;

    private int $qty = 2;

    private int $unitPrice = 200;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['corporate', 'kitchen', 'delivery', 'operation', 'accounts', 'admin'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $this->corporate = $this->makeUser('corporate', 'Corp');
        $this->kitchen = $this->makeUser('kitchen', 'Kit');
        $this->rider = $this->makeUser('delivery', 'Rider');
        $this->ops = $this->makeUser('operation', 'Ops');
        $this->accounts = $this->makeUser('accounts', 'Acct');

        $this->menu = MenuItem::create([
            'name' => 'COD Thali',
            'price' => $this->unitPrice,
            'kitchen_commission' => 50,
            'delivery_commission' => 25,
            'meals_cost' => 70,
            'other_cost' => 5,
        ]);

        $this->oracle = MoneyFlowAssertions::expectedBreakdown(
            $this->unitPrice,
            $this->qty,
            50,
            25,
            5.0
        );
    }

    public function test_cod_process_checks_math_at_every_role_step(): void
    {
        // --- Step 1: Corporate places COD order (checkout) ---
        $order = $this->placeCodOrder();
        $total = $this->unitPrice * $this->qty; // 400

        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'corporate_balance' => 0,
            'kitchen_wallet' => 0,
            'rider_wallet' => 0,
            'rider_ledger' => 0,
            'middo_cash' => 0,
            'amount_due' => $total,
            'amount_paid' => 0,
            'food' => $this->oracle['food'],
            'vat' => $this->oracle['vat'],
            'kitchen_share' => $this->oracle['kitchen'],
            'delivery_share' => $this->oracle['delivery'],
        ], 'corporate.place_cod');

        MoneyFlowAssertions::assertTreeSummary($order, [
            'total' => $total,
            'food' => $this->oracle['food'],
            'vat' => $this->oracle['vat'],
            'due' => $total,
            'paid' => 0,
            'kitchen_share' => $this->oracle['kitchen'],
            'delivery_share' => $this->oracle['delivery'],
        ], 'corporate.place_cod');

        MoneyFlowAssertions::assertEventTypesExist($order, [
            OrderMoneyEvent::TYPE_PLACED,
            OrderMoneyEvent::TYPE_VAT,
        ], 'corporate.place_cod');
        MoneyFlowAssertions::assertOpenPayablesCount($order, 0, 'corporate.place_cod');

        // --- Step 2: Kitchen accepts group (processing) + marks ready ---
        $group = OrderGroup::query()->whereHas('orders', fn ($q) => $q->whereKey($order->id))->firstOrFail();
        $this->assertSame($this->kitchen->id, (int) $group->kitchen_id);

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('markReady', $order->id)
            ->assertSet('errorMessage', null);

        $order->refresh();
        $this->assertSame(OrderTransition::READY, $order->order_status);

        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'kitchen_wallet' => 0,
            'rider_ledger' => 0,
            'amount_due' => $total,
        ], 'kitchen.mark_ready');
        MoneyFlowAssertions::assertOpenPayablesCount($order, 0, 'kitchen.mark_ready');

        // --- Step 3: Ops assigns rider + kitchen packs (dispatch) + rider picks up ---
        $boxes = KitchenBoxFactory::seedSendable($this->kitchen, $this->qty);
        LunchRunFlow::fromReadyToOnTheWay($this->kitchen, $this->rider, $order->fresh(), $boxes);
        $order->refresh();

        $this->assertSame(OrderTransition::ON_THE_WAY_TO_DELIVERY, $order->order_status);

        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'kitchen_wallet' => $this->oracle['kitchen'],
            'rider_ledger' => $this->oracle['delivery'],
            'rider_wallet' => 0,
            'middo_cash' => 0,
            'amount_due' => $total,
        ], 'ops_kitchen_rider.dispatch_and_pickup');

        MoneyFlowAssertions::assertPayable(
            $order,
            PartnerPayable::ROLE_KITCHEN,
            $this->oracle['kitchen'],
            PartnerPayable::STATUS_OPEN,
            'ops_kitchen_rider.dispatch_and_pickup'
        );
        MoneyFlowAssertions::assertPayable(
            $order,
            PartnerPayable::ROLE_DELIVERY,
            $this->oracle['delivery'],
            PartnerPayable::STATUS_OPEN,
            'ops_kitchen_rider.dispatch_and_pickup'
        );
        MoneyFlowAssertions::assertEventTypesExist($order, [
            OrderMoneyEvent::TYPE_KITCHEN_SHARE,
            OrderMoneyEvent::TYPE_DELIVERY_SHARE,
        ], 'ops_kitchen_rider.dispatch_and_pickup');
        MoneyFlowAssertions::assertEventCount($order, OrderMoneyEvent::TYPE_DELIVERY_SHARE, 1, 'ops_kitchen_rider.dispatch_and_pickup');

        // --- Step 4: Rider delivers to consumer ---
        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id)
            ->assertSet('errorMessage', null);

        $order->refresh();
        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'kitchen_wallet' => $this->oracle['kitchen'],
            'rider_ledger' => $this->oracle['delivery'],
            'amount_due' => $total,
            'amount_paid' => 0,
        ], 'rider.deliver');

        // --- Step 5: Rider collects COD cash (commission settled in-kind) ---
        $dueToMiddo = $total - $this->oracle['delivery']; // 400 - 50 = 350

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->assertSet('commissionAmount', $this->oracle['delivery'])
            ->assertSet('dueToMiddo', $dueToMiddo)
            ->call('selectCash')
            ->call('confirmCashPayment')
            ->assertSet('showModal', false);

        $order->refresh();
        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'kitchen_wallet' => $this->oracle['kitchen'],
            'rider_ledger' => 0,
            'rider_wallet' => $dueToMiddo,
            'middo_cash' => 0,
            'amount_due' => 0,
            'amount_paid' => $total,
            'cash_collected' => $total,
            'cash_due_to_middo' => $dueToMiddo,
            'middo_rest' => $this->oracle['middo_rest'],
        ], 'rider.cash_collect');

        MoneyFlowAssertions::assertPayable(
            $order,
            PartnerPayable::ROLE_DELIVERY,
            $this->oracle['delivery'],
            PartnerPayable::STATUS_SETTLED,
            'rider.cash_collect'
        );
        MoneyFlowAssertions::assertEventTypesExist($order, [
            OrderMoneyEvent::TYPE_CASH_COLLECTED,
            OrderMoneyEvent::TYPE_MIDDO_REST,
        ], 'rider.cash_collect');
        MoneyFlowAssertions::assertEventCount($order, OrderMoneyEvent::TYPE_DELIVERY_SHARE, 1, 'rider.cash_collect');
        MoneyFlowAssertions::assertEventCount($order, OrderMoneyEvent::TYPE_MIDDO_REST, 1, 'rider.cash_collect');

        MoneyFlowAssertions::assertTreeSummary($order, [
            'paid' => $total,
            'due' => 0,
            'cash_collected' => $total,
            'middo_rest' => $this->oracle['middo_rest'],
            'kitchen_share' => $this->oracle['kitchen'],
            'delivery_share' => $this->oracle['delivery'],
        ], 'rider.cash_collect');

        // --- Step 6: Rider hands Due to kitchen; kitchen accepts ---
        Livewire::actingAs($this->rider)
            ->test(DeliveryCashHandovers::class)
            ->set('target', CashHandover::TARGET_KITCHEN)
            ->call('toggleOrder', $order->id)
            ->call('createHandover')
            ->assertSet('errorMessage', null);

        $handover = CashHandover::query()->latest('id')->first();
        $this->assertNotNull($handover);
        $this->assertSame($dueToMiddo, (int) $handover->amount);
        $this->assertTrue($handover->isKitchenTarget());

        Livewire::actingAs($this->kitchen)
            ->test(KitchenCashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        // Kitchen share credit − Due debit = 100 − 350 = −250 (Payable to Middo)
        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'kitchen_wallet' => $this->oracle['kitchen'] - $dueToMiddo,
            'rider_wallet' => 0,
            'rider_ledger' => 0,
            'middo_cash' => 0,
        ], 'kitchen.accept_cash_handover');

        // Kitchen share payable remains open until Middo pays out (withdrawal/settlement batch).
        MoneyFlowAssertions::assertPayable(
            $order,
            PartnerPayable::ROLE_KITCHEN,
            $this->oracle['kitchen'],
            PartnerPayable::STATUS_OPEN,
            'kitchen.accept_cash_handover'
        );
        $this->assertSame(0, MiddoCashLedger::balance(), '[kitchen.accept_cash_handover] till untouched');
    }

    public function test_cod_middo_handover_then_accounts_settles_kitchen_share(): void
    {
        $order = $this->placeCodOrder();
        $total = $this->unitPrice * $this->qty;
        $dueToMiddo = $total - $this->oracle['delivery'];

        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('markReady', $order->id);

        $boxes = KitchenBoxFactory::seedSendable($this->kitchen, $this->qty);
        LunchRunFlow::fromReadyToOnTheWay($this->kitchen, $this->rider, $order->fresh(), $boxes);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id);

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

        $handover = CashHandover::query()->latest('id')->firstOrFail();

        Livewire::actingAs($this->ops)
            ->test(OpsCashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        MoneyFlowAssertions::assertRoleBalances($order->fresh(), $this->corporate, $this->kitchen, $this->rider, [
            'kitchen_wallet' => $this->oracle['kitchen'],
            'rider_wallet' => 0,
            'middo_cash' => $dueToMiddo,
        ], 'ops.accept_middo_cash_handover');

        // Accounts settles kitchen share: Middo cash pays kitchen, kitchen wallet clears share.
        $payable = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->firstOrFail();

        OrderMoneyFlow::settlePayable($payable, $this->accounts->id);

        MoneyFlowAssertions::assertRoleBalances($order->fresh(), $this->corporate, $this->kitchen, $this->rider, [
            'kitchen_wallet' => 0,
            'middo_cash' => $dueToMiddo - $this->oracle['kitchen'],
        ], 'accounts.settle_kitchen_payable');

        MoneyFlowAssertions::assertPayable(
            $order,
            PartnerPayable::ROLE_KITCHEN,
            $this->oracle['kitchen'],
            PartnerPayable::STATUS_SETTLED,
            'accounts.settle_kitchen_payable'
        );
        MoneyFlowAssertions::assertEventTypesExist($order, [
            OrderMoneyEvent::TYPE_PAYABLE_SETTLED,
        ], 'accounts.settle_kitchen_payable');
    }

    private function placeCodOrder(): Order
    {
        $today = now('Asia/Dhaka')->toDateString();
        $total = $this->unitPrice * $this->qty;

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => $this->qty,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => $total,
            'amount_paid' => 0,
            'address' => 'Corporate HQ',
            'receiver_name' => 'Recv',
            'receiver_mobile' => '01710000001',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $this->corporate->id,
            'updated_by' => $this->corporate->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-COD-E2E-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
            'created_by' => $this->ops->id,
        ]);
        $group->orders()->attach($order->id);

        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);

        return $order->fresh(['menuItem', 'moneyEvents', 'partnerPayables']);
    }

    private function makeUser(string $role, string $first): User
    {
        return User::create([
            'first_name' => $first,
            'last_name' => 'E2E',
            'mobile' => '013'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => 'password',
            'role_id' => Role::where('name', $role)->value('id'),
            'status' => 'active',
            'balance' => 0,
            'is_mobile_verified' => true,
        ]);
    }
}
