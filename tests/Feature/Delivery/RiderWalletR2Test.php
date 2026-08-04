<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\DeliveredOrders;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoOperatingCost;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderMoneyEvent;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoSettings;
use App\Support\OrderMoneyFlow;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RiderWalletR2Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $rider;

    protected User $customer;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'R2',
            'mobile' => '01910000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'R2',
            'mobile' => '01910000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'R2',
            'mobile' => '01910000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'R2 Lunch',
            'price' => 300,
            'kitchen_commission' => 60,
            'delivery_commission' => 40,
        ]);

        MiddoSettings::updateMealAndKitchenDefaults([
            'delivery_commissions' => [
                DeliveryRunType::CORPORATE_TO_KITCHEN => 15,
                DeliveryRunType::KITCHEN_TO_OPS => 20,
                DeliveryRunType::OPS_TO_KITCHEN => 25,
            ],
        ]);
    }

    protected function dispatchOrder(int $quantity = 1): Order
    {
        $today = now('Asia/Dhaka')->toDateString();
        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => $quantity,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 300 * $quantity,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-R2-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        $boxIds = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $boxIds[] = MiddoBox::create([
                'qr_code_id' => 'MB-R2-'.uniqid().'-'.$i,
                'box_model_type' => 'standard_insulated',
                'asset_status' => 'active',
                'kitchen_id' => $this->kitchen->id,
                'held_by_user_id' => $this->kitchen->id,
                'total_uses_count' => 0,
            ])->id;
        }

        $modal = Livewire::actingAs($this->kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id);
        foreach ($boxIds as $boxId) {
            $modal->call('toggleBox', $boxId);
        }
        $modal->call('dispatchOrder')->assertSet('showModal', false);

        return $order->fresh(['middoBoxes']);
    }

    public function test_lunch_accept_credits_wallet_and_skips_second_accrual(): void
    {
        $order = $this->dispatchOrder(1);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(1, OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_DELIVERY_SHARE)
            ->count());

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id);

        $order->refresh();
        $order->update([
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
            'amount_paid' => 300,
            'cash_collected' => 300,
        ]);
        OrderMoneyFlow::accrueShares($order->fresh(['menuItem', 'orderGroup.kitchen', 'deliveryRider']));

        $this->assertSame(1, OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_DELIVERY_SHARE)
            ->count());
        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));
    }

    public function test_void_after_accept_debits_rider_wallet(): void
    {
        $order = $this->dispatchOrder(1);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id);

        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));

        $order->refresh();
        $order->update(['order_status' => 'cancelled', 'updated_by' => $this->kitchen->id]);

        $this->assertSame(0, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(
            PartnerPayable::STATUS_VOID,
            PartnerPayable::query()
                ->where('order_id', $order->id)
                ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
                ->value('status')
        );
    }

    public function test_corporate_to_kitchen_books_operating_cost_per_box(): void
    {
        $order = $this->dispatchOrder(2);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->call('deliverToConsumer', $order->id);

        $lunchBalance = RiderAccountLedger::balance($this->rider->id);

        Livewire::actingAs($this->rider)
            ->test(DeliveredOrders::class)
            ->call('receiveBoxes', $order->id)
            ->assertSet('errorMessage', null);

        $this->assertSame($lunchBalance + 30, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(2, MiddoOperatingCost::query()
            ->where('cost_type', MiddoOperatingCost::TYPE_RIDER_COMMISSION)
            ->where('run_type', DeliveryRunType::CORPORATE_TO_KITCHEN)
            ->where('rider_user_id', $this->rider->id)
            ->count());
    }
}
