<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\MealOrderGrouper;
use App\Support\OrderMoneyFlow;
use App\Support\OrderPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flaw-hunt diagnostics: asserts economically / policy-correct behavior.
 * Failures here are candidate product flaws, not flaky UI noise.
 */
class FlawHuntDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleName, array $overrides = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::create(array_merge([
            'first_name' => ucfirst($roleName),
            'last_name' => 'User',
            'mobile' => '01310'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $role->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'balance' => 0,
        ], $overrides));
    }

    private function menu(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name' => 'Thali',
            'price' => 500,
            'kitchen_commission' => 100,
            'delivery_commission' => 40,
            'meals_cost' => 80,
            'other_cost' => 10,
        ], $overrides));
    }

    public function test_amount_due_should_honor_discount_for_economic_balance(): void
    {
        $corporate = $this->user('corporate');
        $menu = $this->menu();

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 1000,
            'discount_amount' => 100,
            'amount_paid' => 900,
            'prepaid_amount' => 900,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        // Economically settled: paid (900) + discount (100) covers total (1000).
        $this->assertTrue($order->isPaid(), 'payment_status=paid should mean isPaid');
        $this->assertSame(
            0,
            $order->amountDue(),
            'FLAW: amountDue() ignores discount_amount — customer appears to owe residual after full coupon settlement'
        );
    }

    public function test_deliver_branch_should_treat_discounted_paid_order_as_fully_paid(): void
    {
        $corporate = $this->user('corporate');
        $menu = $this->menu();

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 1000,
            'discount_amount' => 100,
            'amount_paid' => 900,
            'prepaid_amount' => 900,
            'cash_collected' => 0,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'on_the_way_to_delivery',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $wouldMarkDeliveredAndPaid = $order->amountDue() === 0;

        $this->assertTrue(
            $wouldMarkDeliveredAndPaid,
            'FLAW: deliverToConsumer uses amountDue()===0; discounted fully-paid orders stay delivered (unpaid) instead of delivered_and_paid'
        );
    }

    public function test_gateway_residual_must_not_look_like_rider_cash(): void
    {
        $corporate = $this->user('corporate');
        $menu = $this->menu(['price' => 1000]);

        // Simulates OrderPaymentController / EPS residual: amount_paid=total, cash_collected=0, prepaid_amount=0.
        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 1000,
            'amount_paid' => 1000,
            'prepaid_amount' => 0,
            'cash_collected' => 0,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
            'payment_method' => 'gateway',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $this->assertSame(
            0,
            $order->cashCollectedAmount(),
            'FLAW: legacy cashCollectedAmount treats gateway residual (prepaid=0, paid) as full rider cash — corrupts handovers'
        );
    }

    public function test_partner_payables_should_not_exceed_customer_bill_net(): void
    {
        $corporate = $this->user('corporate');
        $menu = $this->menu([
            'price' => 100,
            'kitchen_commission' => 80,
            'delivery_commission' => 80,
        ]);

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 100,
            'discount_amount' => 0,
            'amount_paid' => 100,
            'prepaid_amount' => 100,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $breakdown = OrderMoneyFlow::computeBreakdown($order->fresh('menuItem'));
        $partnerTotal = $breakdown['kitchen_share_amount'] + $breakdown['delivery_share_amount'];
        $billNet = 100;

        $this->assertLessThanOrEqual(
            $billNet,
            $partnerTotal,
            'FLAW: kitchen+delivery commissions can exceed billNet while middo_rest clamps to 0 (negative Middo margin baked in)'
        );
        $this->assertSame(0, $breakdown['middo_rest_amount']);
    }

    public function test_cod_available_for_up_to_three_orders_same_or_multi_day(): void
    {
        // COD tracks the active-order prepay ceiling (3): allowed for 1–3 new lines
        // without forced prepay; blocked once prepayment is required.
        $this->assertTrue(OrderPaymentMethod::allowsCashOnDelivery(false, 1));
        $this->assertTrue(OrderPaymentMethod::allowsCashOnDelivery(false, 2));
        $this->assertTrue(OrderPaymentMethod::allowsCashOnDelivery(false, 3));
        $this->assertFalse(OrderPaymentMethod::allowsCashOnDelivery(true, 1));

        $this->assertSame(
            [
                OrderPaymentMethod::CASH_ON_DELIVERY,
                OrderPaymentMethod::BALANCE,
                OrderPaymentMethod::GATEWAY,
            ],
            OrderPaymentMethod::checkoutOptions(false, 2, 5000, 800)
        );
        $this->assertSame(
            OrderPaymentMethod::CASH_ON_DELIVERY,
            OrderPaymentMethod::resolveCheckout(null, false, 2, 5000, 800)
        );
        $this->assertSame(
            [
                OrderPaymentMethod::CASH_ON_DELIVERY,
                OrderPaymentMethod::GATEWAY,
            ],
            OrderPaymentMethod::checkoutOptions(false, 1, 0, 420)
        );
        $this->assertFalse(OrderPaymentMethod::balanceSelectable(0, 420));
    }

    public function test_auto_grouper_should_reject_order_qty_above_group_capacity(): void
    {
        config(['middo.auto_meal_group_quantity' => 10]);

        $corporate = $this->user('corporate', ['area_id' => null]);
        // area required — create city/area lightly via order.area_id
        $city = \App\Models\City::create(['name' => 'Dhaka']);
        $area = \App\Models\Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $corporate->update(['area_id' => $area->id]);

        $menu = $this->menu();
        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 11,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 5500,
            'amount_paid' => 0,
            'prepaid_amount' => 0,
            'area_id' => $area->id,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $threw = false;
        try {
            app(MealOrderGrouper::class)->assignOrder($order->load('user'), $corporate->id);
        } catch (\Throwable $e) {
            $threw = true;
        }

        $groupQty = 0;
        if (! $threw) {
            $group = OrderGroup::query()
                ->whereHas('orders', fn ($q) => $q->where('orders.id', $order->id))
                ->withSum('orders', 'quantity')
                ->first();
            $groupQty = (int) ($group?->orders_sum_quantity ?? 0);
        }

        $this->assertTrue(
            $threw || $groupQty <= 10,
            'FLAW: MealOrderGrouper creates over-capacity solo groups when order.quantity > auto_meal_group_quantity'
        );
    }

    public function test_is_paid_true_can_block_cash_collection_while_amount_due_positive(): void
    {
        $corporate = $this->user('corporate');
        $menu = $this->menu();

        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->addDay()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 1000,
            'discount_amount' => 100,
            'amount_paid' => 900,
            'prepaid_amount' => 900,
            'cash_collected' => 0,
            'address' => 'Test',
            'receiver_name' => 'R',
            'receiver_mobile' => '01710123456',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'balance',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);

        $stuck = $order->isPaid() && $order->amountDue() > 0;

        $this->assertFalse(
            $stuck,
            'FLAW: isPaid() true while amountDue()>0 — PaymentModal skips cash collect; residual uncollectable / status stuck'
        );
    }
}
