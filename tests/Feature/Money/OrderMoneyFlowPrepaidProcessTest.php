<?php

namespace Tests\Feature\Money;

use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Kitchen\ActiveOrders;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderMoneyEvent;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\MiddoCashLedger;
use App\Support\OrderMoneyFlow;
use App\Support\OrderTransition;
use App\Support\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\KitchenBoxFactory;
use Tests\Support\LunchRunFlow;
use Tests\Support\MoneyFlowAssertions;
use Tests\TestCase;

/**
 * Prepaid (wallet) process-flow E2E: corporate pays at checkout, then kitchen/ops/rider
 * fulfill — math checked for every role after each step (no COD cash path).
 */
class OrderMoneyFlowPrepaidProcessTest extends TestCase
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

        $this->corporate = $this->makeUser('corporate', 'Corp', balance: 5_000);
        $this->kitchen = $this->makeUser('kitchen', 'Kit');
        $this->rider = $this->makeUser('delivery', 'Rider');
        $this->ops = $this->makeUser('operation', 'Ops');
        $this->accounts = $this->makeUser('accounts', 'Acct');

        $this->menu = MenuItem::create([
            'name' => 'Prepaid Thali',
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

    public function test_prepaid_wallet_process_checks_math_at_every_role_step(): void
    {
        $total = $this->unitPrice * $this->qty; // 400
        $walletBefore = (int) $this->corporate->balance;

        // --- Step 1: Corporate checkout pays from Middo Balance ---
        WalletLedger::debit(
            $this->corporate,
            $total,
            'E2E prepaid checkout',
            null
        );

        $order = $this->placePrepaidOrder($total);

        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'corporate_balance' => $walletBefore - $total,
            'kitchen_wallet' => 0,
            'rider_ledger' => 0,
            'rider_wallet' => 0,
            'middo_cash' => 0,
            'amount_due' => 0,
            'amount_paid' => $total,
            'food' => $this->oracle['food'],
            'vat' => $this->oracle['vat'],
            'kitchen_share' => $this->oracle['kitchen'],
            'delivery_share' => $this->oracle['delivery'],
        ], 'corporate.wallet_checkout');

        MoneyFlowAssertions::assertEventTypesExist($order, [
            OrderMoneyEvent::TYPE_PLACED,
            OrderMoneyEvent::TYPE_VAT,
            OrderMoneyEvent::TYPE_PAYMENT,
        ], 'corporate.wallet_checkout');

        MoneyFlowAssertions::assertTreeSummary($order, [
            'total' => $total,
            'paid' => $total,
            'due' => 0,
            'food' => $this->oracle['food'],
            'vat' => $this->oracle['vat'],
        ], 'corporate.wallet_checkout');

        // --- Step 2: Kitchen ready ---
        Livewire::actingAs($this->kitchen)
            ->test(ActiveOrders::class)
            ->call('markReady', $order->id)
            ->assertSet('errorMessage', null);

        MoneyFlowAssertions::assertRoleBalances($order->fresh(), $this->corporate, $this->kitchen, $this->rider, [
            'corporate_balance' => $walletBefore - $total,
            'kitchen_wallet' => 0,
            'amount_due' => 0,
        ], 'kitchen.mark_ready');

        // --- Step 3: Ops assign + kitchen dispatch + rider pickup ---
        $boxes = KitchenBoxFactory::seedSendable($this->kitchen, $this->qty);
        LunchRunFlow::fromReadyToOnTheWay($this->kitchen, $this->rider, $order->fresh(), $boxes);
        $order->refresh();

        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'corporate_balance' => $walletBefore - $total,
            'kitchen_wallet' => $this->oracle['kitchen'],
            'rider_ledger' => $this->oracle['delivery'],
            'rider_wallet' => 0,
            'middo_cash' => 0,
            'amount_due' => 0,
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

        // --- Step 4: Rider delivers (prepaid → delivered_and_paid, no cash) ---
        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id)
            ->assertSet('errorMessage', null);

        $order->refresh();

        // Prepaid delivery should not create rider Due cash.
        MoneyFlowAssertions::assertRoleBalances($order, $this->corporate, $this->kitchen, $this->rider, [
            'corporate_balance' => $walletBefore - $total,
            'kitchen_wallet' => $this->oracle['kitchen'],
            'rider_ledger' => $this->oracle['delivery'],
            'rider_wallet' => 0,
            'middo_cash' => 0,
            'amount_due' => 0,
            'amount_paid' => $total,
            'cash_collected' => 0,
            'middo_rest' => $this->oracle['middo_rest'],
        ], 'rider.deliver_prepaid');

        MoneyFlowAssertions::assertEventTypesExist($order, [
            OrderMoneyEvent::TYPE_MIDDO_REST,
        ], 'rider.deliver_prepaid');
        MoneyFlowAssertions::assertEventCount($order, OrderMoneyEvent::TYPE_MIDDO_REST, 1, 'rider.deliver_prepaid');
        MoneyFlowAssertions::assertEventCount($order, OrderMoneyEvent::TYPE_DELIVERY_SHARE, 1, 'rider.deliver_prepaid');

        // Delivery payable may still be open (commission on ledger until withdrawal/settle).
        MoneyFlowAssertions::assertPayable(
            $order,
            PartnerPayable::ROLE_DELIVERY,
            $this->oracle['delivery'],
            PartnerPayable::STATUS_OPEN,
            'rider.deliver_prepaid'
        );

        MoneyFlowAssertions::assertTreeSummary($order, [
            'paid' => $total,
            'due' => 0,
            'middo_rest' => $this->oracle['middo_rest'],
            'kitchen_share' => $this->oracle['kitchen'],
            'delivery_share' => $this->oracle['delivery'],
        ], 'rider.deliver_prepaid');

        // --- Step 5: Accounts settles kitchen + seeds till ---
        MiddoCashLedger::credit(10_000, 'seed', null, null, 'Seed', $this->accounts->id);

        $kitchenPayable = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->firstOrFail();

        OrderMoneyFlow::settlePayable($kitchenPayable, $this->accounts->id);

        MoneyFlowAssertions::assertRoleBalances($order->fresh(), $this->corporate, $this->kitchen, $this->rider, [
            'kitchen_wallet' => 0,
            'rider_ledger' => $this->oracle['delivery'],
            'middo_cash' => 10_000 - $this->oracle['kitchen'],
            'corporate_balance' => $walletBefore - $total,
        ], 'accounts.settle_kitchen');

        MoneyFlowAssertions::assertPayable(
            $order,
            PartnerPayable::ROLE_KITCHEN,
            $this->oracle['kitchen'],
            PartnerPayable::STATUS_SETTLED,
            'accounts.settle_kitchen'
        );
    }

    private function placePrepaidOrder(int $total): Order
    {
        $today = now('Asia/Dhaka')->toDateString();

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => $this->qty,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => $total,
            'amount_paid' => $total,
            'prepaid_amount' => $total,
            'address' => 'Corporate HQ',
            'receiver_name' => 'Recv',
            'receiver_mobile' => '01710000002',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $this->corporate->id,
            'updated_by' => $this->corporate->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-PREPAID-E2E-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
            'created_by' => $this->ops->id,
        ]);
        $group->orders()->attach($order->id);

        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);

        return $order->fresh(['menuItem', 'moneyEvents', 'partnerPayables']);
    }

    private function makeUser(string $role, string $first, int $balance = 0): User
    {
        return User::create([
            'first_name' => $first,
            'last_name' => 'E2E',
            'mobile' => '014'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => 'password',
            'role_id' => Role::where('name', $role)->value('id'),
            'status' => 'active',
            'balance' => $balance,
            'is_mobile_verified' => true,
        ]);
    }
}
