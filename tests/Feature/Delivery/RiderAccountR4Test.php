<?php

namespace Tests\Feature\Delivery;

use App\Livewire\Delivery\Account;
use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Livewire\Shared\RiderMoneyApprovals;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\PartnerPayable;
use App\Models\RiderWithdrawalRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\MiddoCashLedger;
use App\Support\OrderTransition;
use App\Support\RiderAccountLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RiderAccountR4Test extends TestCase
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
            'last_name' => 'R4',
            'mobile' => '01940000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'R4',
            'mobile' => '01940000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);
        $this->customer = User::create([
            'first_name' => 'Corp',
            'last_name' => 'R4',
            'mobile' => '01940000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);
        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'R4',
            'mobile' => '01940000004',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'R4 Lunch',
            'price' => 200,
            'kitchen_commission' => 50,
            'delivery_commission' => 40,
        ]);

        // Seed Middo cash so withdrawal debit succeeds.
        MiddoCashLedger::credit(1000, 'seed', null, null, 'Test seed', $this->admin->id);
    }

    protected function acceptPrepaidRun(): Order
    {
        $today = now('Asia/Dhaka')->toDateString();
        $order = Order::create([
            'user_id' => $this->customer->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'prepaid_amount' => 200,
            'amount_paid' => 200,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'paid',
        ]);
        $group = OrderGroup::create([
            'name' => 'GRP-R4-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-R4-'.uniqid(),
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
            ->assertSet('errorMessage', null)
            ->call('deliverToConsumer', $order->id)
            ->assertSet('errorMessage', null);

        return $order->fresh();
    }

    public function test_account_page_and_approve_withdrawal(): void
    {
        $this->acceptPrepaidRun();
        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(0, (int) $this->rider->fresh()->balance);

        $this->actingAs($this->rider)
            ->get(route('delivery.account'))
            ->assertOk()
            ->assertSee('Middo owes you')
            ->assertSee('৳40');

        Livewire::actingAs($this->rider)
            ->test(Account::class)
            ->set('withdrawAmount', 40)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', '')
            ->assertSet('statusMessage', fn ($m) => str_contains((string) $m, 'submitted'));

        $request = RiderWithdrawalRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame(40, (int) $request->amount);

        Livewire::actingAs($this->admin)
            ->test(RiderMoneyApprovals::class)
            ->call('approveWithdrawal', $request->id)
            ->assertSet('errorMessage', '');

        $this->assertSame(0, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(960, MiddoCashLedger::balance());
        $this->assertSame(
            PartnerPayable::STATUS_SETTLED,
            PartnerPayable::query()
                ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
                ->where('beneficiary_user_id', $this->rider->id)
                ->value('status')
        );
        $this->assertSame(RiderWithdrawalRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_withdrawal_blocked_when_due_cash_held(): void
    {
        $this->acceptPrepaidRun();
        $this->rider->update(['balance' => 100]);

        Livewire::actingAs($this->rider)
            ->test(Account::class)
            ->set('withdrawAmount', 40)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', fn ($m) => str_contains((string) $m, 'Due to Middo'));

        $this->assertSame(0, RiderWithdrawalRequest::query()->count());
    }

    public function test_reject_leaves_wallet_unchanged(): void
    {
        $this->acceptPrepaidRun();

        Livewire::actingAs($this->rider)
            ->test(Account::class)
            ->set('withdrawAmount', 40)
            ->call('requestWithdrawal');

        $request = RiderWithdrawalRequest::query()->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(RiderMoneyApprovals::class)
            ->call('rejectWithdrawal', $request->id)
            ->assertSet('errorMessage', '');

        $this->assertSame(40, RiderAccountLedger::balance($this->rider->id));
        $this->assertSame(RiderWithdrawalRequest::STATUS_REJECTED, $request->fresh()->status);
        $this->assertSame(1000, MiddoCashLedger::balance());
    }

    public function test_second_pending_request_blocked(): void
    {
        $this->acceptPrepaidRun();

        Livewire::actingAs($this->rider)
            ->test(Account::class)
            ->set('withdrawAmount', 20)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', '');

        Livewire::actingAs($this->rider)
            ->test(Account::class)
            ->set('withdrawAmount', 20)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', fn ($m) => str_contains((string) $m, 'pending'));

        $this->assertSame(1, RiderWithdrawalRequest::query()->count());
    }

    public function test_dispatch_shows_commission_when_configured(): void
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
            'name' => 'GRP-R4-SHOW',
            'menu_id' => $this->menu->id,
            'delivery_date' => $today,
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);
        OrderTransition::apply($order->fresh(), OrderTransition::PROCESSING);
        OrderTransition::apply($order->fresh(), OrderTransition::READY);
        $box = MiddoBox::create([
            'qr_code_id' => 'MB-R4-SHOW',
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
            ->call('dispatchOrder');

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->assertSee('Commission ৳40', false);

        $this->menu->update(['delivery_commission' => 0]);

        Livewire::actingAs($this->rider)
            ->test(KitchenDispatches::class)
            ->assertDontSee('Commission ৳', false);
    }
}
