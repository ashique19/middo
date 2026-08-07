<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Delivery\PaymentModal;
use App\Livewire\Shared\AccountsHub;
use App\Livewire\Shared\CodDueReconPage;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\CodDueRecon;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NextWaveN3PartialCashTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $rider;

    protected User $customer;

    protected User $accounts;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $accountsRole = Role::create(['name' => 'accounts']);
        Role::create(['name' => 'operation']);
        Role::create(['name' => 'admin']);

        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'N3', 'mobile' => '01993000001',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'N3', 'mobile' => '01993000002',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active',
        ]);
        $this->customer = User::create([
            'first_name' => 'Corp', 'last_name' => 'N3', 'mobile' => '01993000003',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
        ]);
        $this->accounts = User::create([
            'first_name' => 'Accounts', 'last_name' => 'N3', 'mobile' => '01993000004',
            'password' => 'password', 'role_id' => $accountsRole->id, 'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'N3 Lunch', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
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
            'name' => 'GRP-N3-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-N3-'.uniqid(),
            'box_model_type' => 'standard_insulated',
            'asset_status' => 'active',
            'kitchen_id' => $this->kitchen->id,
            'held_by_user_id' => $this->kitchen->id,
            'total_uses_count' => 0,
        ]);

        \Tests\Support\LunchRunFlow::fromReadyToOnTheWay(
            $this->kitchen,
            $this->rider,
            $order->fresh(),
            $box
        );

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->call('deliverToConsumer', $order->id)
            ->assertSet('errorMessage', null);

        return $order->fresh();
    }

    public function test_short_cash_keeps_delivered_with_residual_and_logs(): void
    {
        $order = $this->deliverOrder();
        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectCash')
            ->set('cashCollectAmount', 100)
            ->set('shortReason', 'Customer short — will pay rest online')
            ->call('confirmCashPayment')
            ->assertSet('showModal', false)
            ->assertSet('errorMessage', null);

        $order->refresh();
        $this->assertSame(OrderTransition::DELIVERED, $order->order_status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(100, (int) $order->cash_collected);
        $this->assertSame(100, (int) $order->amount_paid);
        $this->assertSame(100, $order->amountDue());
        $this->assertTrue($order->hasCustomerCashShortfall());
        $this->assertSame(60, (int) $order->cash_due_to_middo); // 100 − 40 commission
        $this->assertSame(60, (int) $this->rider->fresh()->balance);
        $this->assertSame(0, RiderAccountLedger::balance($this->rider->id));
        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'event' => 'cash_short_collect',
        ]);
        $this->assertSame(
            PartnerPayable::STATUS_SETTLED,
            PartnerPayable::query()
                ->where('order_id', $order->id)
                ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
                ->value('status')
        );
    }

    public function test_second_cash_collect_closes_shortfall(): void
    {
        $order = $this->deliverOrder();

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectCash')
            ->set('cashCollectAmount', 80)
            ->set('shortReason', 'Partial now')
            ->call('confirmCashPayment')
            ->assertSet('errorMessage', null);

        $order->refresh();
        $this->assertSame(120, $order->amountDue());
        $this->assertSame(40, (int) $order->cash_due_to_middo); // 80 − 40

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->assertSet('amountDue', 120)
            ->assertSet('openCommission', 0)
            ->call('selectCash')
            ->set('cashCollectAmount', 120)
            ->call('confirmCashPayment')
            ->assertSet('errorMessage', null);

        $order->refresh();
        $this->assertSame(OrderTransition::DELIVERED_AND_PAID, $order->order_status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(200, (int) $order->cash_collected);
        $this->assertSame(0, $order->amountDue());
        $this->assertSame(160, (int) $order->cash_due_to_middo); // 40 + 120
        $this->assertSame(160, (int) $this->rider->fresh()->balance);
    }

    public function test_short_collect_requires_reason(): void
    {
        $order = $this->deliverOrder();

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectCash')
            ->set('cashCollectAmount', 50)
            ->set('shortReason', '')
            ->call('confirmCashPayment')
            ->assertSet('errorMessage', 'Add a short reason when collecting less than the full amount due.');

        $this->assertSame(0, (int) $order->fresh()->cash_collected);
    }

    public function test_cod_recon_and_accounts_hub_show_shortfall(): void
    {
        $order = $this->deliverOrder();

        Livewire::actingAs($this->rider)
            ->test(PaymentModal::class)
            ->call('openModal', $order->id)
            ->call('selectCash')
            ->set('cashCollectAmount', 90)
            ->set('shortReason', 'Short for recon')
            ->call('confirmCashPayment');

        $report = CodDueRecon::forDate(now('Asia/Dhaka')->toDateString());
        $this->assertSame(1, $report['totals']['short_count']);
        $this->assertSame(110, $report['totals']['shortfall']);
        $this->assertCount(1, $report['short_orders']);
        $this->assertSame($order->id, $report['short_orders'][0]['id']);

        Livewire::actingAs($this->accounts)
            ->test(CodDueReconPage::class)
            ->assertSee('Customer shortfall')
            ->assertSee('Short collections')
            ->assertSee('#'.$order->id);

        Livewire::actingAs($this->accounts)
            ->test(AccountsHub::class)
            ->assertSee('Short cash collections')
            ->assertSee('#'.$order->id);
    }
}
