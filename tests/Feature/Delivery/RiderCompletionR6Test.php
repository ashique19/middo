<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\CashHandovers as DeliveryCashHandovers;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Delivery\PaymentModal;
use App\Livewire\Kitchen\CashHandovers as KitchenCashHandovers;
use App\Livewire\Operation\AssignMiddoBoxesModal;
use App\Livewire\Shared\AccountsHub;
use App\Models\CashHandover;
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
use App\Support\KitchenAccountLedger;
use App\Support\MiddoSettings;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RiderCompletionR6Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $rider;

    protected User $customer;

    protected User $admin;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $adminRole = Role::create(['name' => 'admin']);

        $this->kitchen = User::create([
            'first_name' => 'Kitchen',
            'last_name' => 'R6',
            'mobile' => '01960000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'area_id' => null,
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'R6',
            'mobile' => '01960000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'R6',
            'mobile' => '01960000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'R6',
            'mobile' => '01960000004',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'R6 Lunch',
            'price' => 200,
            'kitchen_commission' => 50,
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

    protected function dispatchAndAccept(int $qty = 1): Order
    {
        $today = now('Asia/Dhaka')->toDateString();
        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => $qty,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 200 * $qty,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-R6-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        $boxes = [];
        for ($i = 1; $i <= $qty; $i++) {
            $boxes[] = MiddoBox::create([
                'qr_code_id' => 'MB-R6-'.uniqid().'-'.$i,
                'box_model_type' => 'standard_insulated',
                'asset_status' => 'active',
                'kitchen_id' => $this->kitchen->id,
                'held_by_user_id' => $this->kitchen->id,
                'total_uses_count' => 0,
            ]);
        }

        $order = \Tests\Support\LunchRunFlow::fromReadyToOnTheWay(
            $this->kitchen,
            $this->rider,
            $order->fresh(),
            $boxes
        );

        return $order->load(['middoBoxes']);
    }

    public function test_lunch_commission_cash_due_handover_and_no_double_accrual(): void
    {
        $order = $this->dispatchAndAccept(1);
        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(1, OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_DELIVERY_SHARE)
            ->count());

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id);

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectCash')
            ->call('confirmCashPayment')
            ->assertSet('showModal', false);

        $order->refresh();
        $this->assertSame(160, (int) $order->cash_due_to_middo);
        $this->assertSame(160, (int) $this->rider->fresh()->balance);
        $this->assertSame(0, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(1, OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_DELIVERY_SHARE)
            ->count());

        Livewire::actingAs($this->rider)
            ->test(DeliveryCashHandovers::class)
            ->set('target', CashHandover::TARGET_KITCHEN)
            ->call('toggleOrder', $order->id)
            ->call('createHandover')
            ->assertSet('errorMessage', null);

        $handover = CashHandover::query()->firstOrFail();
        $this->assertSame(160, (int) $handover->amount);

        Livewire::actingAs($this->kitchen)
            ->test(KitchenCashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(0, (int) $this->rider->fresh()->balance);
        // Kitchen share 50 − Due 160 = −110
        $this->assertSame(-110, KitchenAccountLedger::balance($this->kitchen->id));
    }

    public function test_ops_to_kitchen_books_settings_commission_per_box(): void
    {
        $boxA = MiddoBox::create([
            'qr_code_id' => 'MB-OPS-A',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'kitchen_id' => null,
            'held_by_user_id' => null,
            'total_uses_count' => 0,
        ]);
        $boxB = MiddoBox::create([
            'qr_code_id' => 'MB-OPS-B',
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'at_middo_warehouse',
            'kitchen_id' => null,
            'held_by_user_id' => null,
            'total_uses_count' => 0,
        ]);

        Livewire::actingAs($this->admin)
            ->test(AssignMiddoBoxesModal::class)
            ->call('openModal', [$boxA->id, $boxB->id])
            ->set('selectedKitchenId', $this->kitchen->id)
            ->set('selectedRiderId', $this->rider->id)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertSame(50, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(2, MiddoOperatingCost::query()
            ->where('run_type', DeliveryRunType::OPS_TO_KITCHEN)
            ->where('rider_user_id', $this->rider->id)
            ->count());
    }

    public function test_accounts_hub_blocks_delivery_settle(): void
    {
        $order = $this->dispatchAndAccept(1);
        $payable = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
            ->firstOrFail();

        $walletBefore = RiderAccountLedger::balance($this->rider->id);

        Livewire::actingAs($this->admin)
            ->test(AccountsHub::class)
            ->call('settlePayable', $payable->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'Rider money'));

        $this->assertSame(PartnerPayable::STATUS_OPEN, $payable->fresh()->status);
        $this->assertSame($walletBefore, RiderAccountLedger::balance($this->rider->id));
    }
}
