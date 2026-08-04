<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Operation\CashHandovers;
use App\Livewire\Shared\CorporateShow;
use App\Livewire\Shared\KitchenMoneyApprovals;
use App\Livewire\Shared\OrderShow;
use App\Livewire\Shared\RiderMoneyApprovals;
use App\Models\CashHandover;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\MiddoCashLedger;
use App\Support\OrderLens;
use App\Support\StaffPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountsMoneyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $accounts;

    protected User $admin;

    protected User $corporate;

    protected User $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        $accountsRole = Role::create(['name' => 'accounts']);
        $adminRole = Role::create(['name' => 'admin']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        Role::create(['name' => 'delivery']);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'A', 'mobile' => '01960000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->accounts = User::create([
            'first_name' => 'Accounts', 'last_name' => 'A', 'mobile' => '01960000002',
            'password' => 'password', 'role_id' => $accountsRole->id, 'status' => 'active',
        ]);
        $this->admin = User::create([
            'first_name' => 'Admin', 'last_name' => 'A', 'mobile' => '01960000003',
            'password' => 'password', 'role_id' => $adminRole->id, 'status' => 'active',
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'A', 'mobile' => '01960000004',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
            'balance' => 1000, 'company_name' => 'A Corp',
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'A', 'mobile' => '01960000005',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
        ]);
    }

    public function test_staff_portal_dual_control_matrix(): void
    {
        $this->assertTrue(StaffPortal::canAcceptHandover('operation'));
        $this->assertTrue(StaffPortal::canAcceptHandover('accounts'));
        $this->assertTrue(StaffPortal::canProposeHandoverReject('operation'));
        $this->assertFalse(StaffPortal::canProposeHandoverReject('accounts'));
        $this->assertTrue(StaffPortal::canConfirmHandoverReject('accounts'));
        $this->assertFalse(StaffPortal::canConfirmHandoverReject('operation'));
        $this->assertTrue(StaffPortal::canWriteMoney('accounts'));
        $this->assertFalse(StaffPortal::canWriteMoney('operation'));
    }

    public function test_accounts_and_ops_can_accept_handover(): void
    {
        $riderRole = Role::query()->where('name', 'delivery')->first();
        $rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'A', 'mobile' => '01960000006',
            'password' => 'password', 'role_id' => $riderRole->id, 'status' => 'active',
            'balance' => 200,
        ]);

        $handover = CashHandover::create([
            'rider_id' => $rider->id,
            'amount' => 100,
            'target' => CashHandover::TARGET_MIDDO,
            'status' => CashHandover::STATUS_PENDING,
        ]);

        Livewire::actingAs($this->accounts)
            ->test(CashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(CashHandover::STATUS_ACCEPTED, $handover->fresh()->status);
        $this->assertSame(100, MiddoCashLedger::balance());
        $this->assertSame(100, (int) $rider->fresh()->balance);
    }

    public function test_ops_cannot_confirm_reject_or_write_money(): void
    {
        $riderRole = Role::query()->where('name', 'delivery')->first();
        $rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'B', 'mobile' => '01960000007',
            'password' => 'password', 'role_id' => $riderRole->id, 'status' => 'active',
            'balance' => 50,
        ]);
        $handover = CashHandover::create([
            'rider_id' => $rider->id,
            'amount' => 50,
            'target' => CashHandover::TARGET_MIDDO,
            'status' => CashHandover::STATUS_PROPOSED_REJECT,
            'rejection_proposed_by' => $this->ops->id,
            'rejection_proposed_at' => now(),
        ]);

        Livewire::actingAs($this->ops)
            ->test(CashHandovers::class)
            ->call('confirmReject', $handover->id)
            ->assertForbidden();

        Livewire::actingAs($this->ops)
            ->test(KitchenMoneyApprovals::class)
            ->call('approveWithdrawal', 1)
            ->assertForbidden();

        Livewire::actingAs($this->ops)
            ->test(RiderMoneyApprovals::class)
            ->call('approveWithdrawal', 1)
            ->assertForbidden();
    }

    public function test_corporate_wallet_adjust_accounts_only(): void
    {
        $opsView = Livewire::actingAs($this->ops)
            ->test(CorporateShow::class, ['corporate' => $this->corporate]);
        $this->assertFalse($opsView->instance()->canAdjustWallet());
        $opsView->set('adjustDirection', 'credit')
            ->set('adjustAmount', '100')
            ->set('adjustReason', 'goodwill')
            ->call('postWalletAdjustment')
            ->assertForbidden();

        $this->assertSame(1000, (int) $this->corporate->fresh()->balance);

        Livewire::actingAs($this->accounts)
            ->test(CorporateShow::class, ['corporate' => $this->corporate])
            ->set('adjustDirection', 'credit')
            ->set('adjustAmount', '100')
            ->set('adjustReason', 'goodwill')
            ->call('postWalletAdjustment')
            ->assertSee('Credit ৳100 posted');

        $this->assertSame(1100, (int) $this->corporate->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->corporate->id,
            'type' => WalletTransaction::TYPE_ADJUSTMENT,
            'amount' => 100,
        ]);
    }

    public function test_accounts_can_switch_order_lenses_but_not_force_cancel(): void
    {
        $menu = MenuItem::create([
            'name' => 'A Thali', 'price' => 200,
            'kitchen_commission' => 50, 'delivery_commission' => 40,
        ]);
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->assertTrue(OrderLens::canViewStaffLenses('accounts'));
        $this->assertFalse(OrderLens::isStaff('accounts'));

        $show = Livewire::actingAs($this->accounts)
            ->test(OrderShow::class, ['order' => $order]);
        $this->assertTrue($show->instance()->canSwitchLenses());
        $this->assertFalse($show->instance()->canIntervene());
        $show->set('lens', OrderLens::CORPORATE)
            ->assertSet('lens', OrderLens::CORPORATE)
            ->call('forceCancel')
            ->assertForbidden();
    }

    public function test_accounts_routes_render(): void
    {
        $this->actingAs($this->accounts)->get(route('accounts.cash-handovers'))->assertOk();
        $this->actingAs($this->accounts)->get(route('accounts.corporates.index'))->assertOk();
        $this->actingAs($this->accounts)->get(route('accounts.corporates.show', $this->corporate))->assertOk();
        $this->actingAs($this->ops)->get(route('accounts.cash-handovers'))->assertRedirect();
    }
}
