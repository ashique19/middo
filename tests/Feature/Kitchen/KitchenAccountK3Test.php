<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\Account;
use App\Livewire\Kitchen\CashHandovers;
use App\Livewire\Shared\KitchenMoneyApprovals;
use App\Models\CashHandover;
use App\Models\CashHandoverOrder;
use App\Models\KitchenMiddoTransfer;
use App\Models\KitchenWithdrawalRequest;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\MiddoBankAccount;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\MiddoCashLedger;
use App\Support\PayoutChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenAccountK3Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $admin;

    protected User $otherKitchen;

    protected MenuItem $menu;

    protected User $corporate;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $adminRole = Role::create(['name' => 'admin']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'delivery']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01740000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->otherKitchen = User::create([
            'first_name' => 'Other',
            'last_name' => 'Kitchen',
            'mobile' => '01740000002',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01740000003',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01740000004',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'Thali',
            'price' => 200,
            'kitchen_commission' => 50,
            'delivery_commission' => 0,
        ]);
    }

    protected function accrueKitchenShare(int $amount = 50): PartnerPayable
    {
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'amount_paid' => 200,
            'address' => 'Test',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'created_by' => $this->corporate->id,
            'updated_by' => $this->corporate->id,
        ]);

        $group = OrderGroup::create([
            'name' => 'GRP-K3-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $order->update([
            'dispatched_at' => now(),
            'order_status' => 'delivered_and_paid',
        ]);

        $payable = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
            ->first();

        $this->assertNotNull($payable);
        $this->assertSame($amount, (int) $payable->amount);
        $this->assertSame($amount, KitchenAccountLedger::balance($this->kitchen->id));

        return $payable;
    }

    public function test_dispatch_credits_kitchen_wallet(): void
    {
        $this->accrueKitchenShare(50);
        $this->assertSame(50, KitchenAccountLedger::balance($this->kitchen->id));
    }

    public function test_account_quick_actions_follow_wallet_direction(): void
    {
        $this->accrueKitchenShare(50);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.account'))
            ->assertOk()
            ->assertSee('Request withdrawal')
            ->assertDontSee('Send money to Middo')
            ->assertDontSee('Cash handovers →');

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.cash-handovers'))
            ->assertOk()
            ->assertSee('Receivable from Middo', false)
            ->assertDontSee('Payable to Middo', false);

        KitchenAccountLedger::debit($this->kitchen->id, 200, 'cash_received', null, null, 'Surplus seed', $this->admin->id);
        $this->assertSame(-150, KitchenAccountLedger::balance($this->kitchen->id));

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.account'))
            ->assertOk()
            ->assertSee('Send money to Middo')
            ->assertDontSee('Request withdrawal')
            ->assertDontSee('Cash handovers →');

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.cash-handovers'))
            ->assertOk()
            ->assertSee('Payable to Middo', false)
            ->assertDontSee('Receivable from Middo', false);
    }

    public function test_cash_handover_debits_kitchen_wallet_and_allows_negative(): void
    {
        $this->accrueKitchenShare(50);

        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Cash',
            'mobile' => '01740000019',
            'password' => 'password',
            'role_id' => Role::where('name', 'delivery')->value('id'),
            'status' => 'active',
            'balance' => 500,
        ]);

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'cash_collected' => 200,
            'address' => 'A',
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
        ]);
        OrderGroup::create([
            'name' => 'GRP-SURPLUS',
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $this->kitchen->id,
        ])->orders()->attach($order->id);

        $handover = CashHandover::create(['rider_id' => $rider->id, 'amount' => 200, 'status' => 'pending']);
        CashHandoverOrder::create(['cash_handover_id' => $handover->id, 'order_id' => $order->id, 'amount' => 200]);

        Livewire::actingAs($this->kitchen)
            ->test(CashHandovers::class)
            ->call('accept', $handover->id)
            ->assertSet('errorMessage', null);

        // 50 credit − 200 cash = kitchen owes Middo 150
        $this->assertSame(-150, KitchenAccountLedger::balance($this->kitchen->id));
        $this->assertSame(0, MiddoCashLedger::balance());
        $this->assertSame(300, (int) $rider->fresh()->balance);
    }

    public function test_kitchen_can_request_and_admin_approves_withdrawal(): void
    {
        $this->accrueKitchenShare(50);
        MiddoCashLedger::credit(500, 'seed', null, null, 'Seed', $this->admin->id);

        $bank = MiddoBankAccount::create([
            'name' => 'Ops Bank',
            'bank_name' => 'BRAC',
            'is_default' => true,
            'is_active' => true,
        ]);
        \App\Support\MiddoBankLedger::credit(
            (int) $bank->id,
            1000,
            \App\Models\MiddoBankLedgerEntry::TYPE_ADJUSTMENT,
            null,
            null,
            'Seed bank',
            $this->admin->id
        );

        $this->kitchen->storePayoutMethods([
            'preferred' => PayoutChannel::BKASH,
            PayoutChannel::BKASH => ['mobile' => '01711112222'],
        ]);
        $this->kitchen->save();

        Livewire::actingAs($this->kitchen)
            ->test(Account::class)
            ->set('payoutChannel', PayoutChannel::BKASH)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', '');

        $request = KitchenWithdrawalRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame(50, (int) $request->amount);
        $this->assertSame(PayoutChannel::BKASH, $request->payout_channel);
        $this->assertSame(0, KitchenAccountLedger::balance($this->kitchen->id));
        $this->assertNotNull($request->kitchen_ledger_entry_id);

        Livewire::actingAs($this->admin)
            ->test(KitchenMoneyApprovals::class)
            ->set("approveBankAccountId.{$request->id}", $bank->id)
            ->set("approveAttachment.{$request->id}", UploadedFile::fake()->image('proof.jpg'))
            ->call('approveWithdrawal', $request->id)
            ->assertSet('errorMessage', '');

        $this->assertSame(0, KitchenAccountLedger::balance($this->kitchen->id));
        $this->assertSame(500, MiddoCashLedger::balance());
        $this->assertSame(950, \App\Support\MiddoBankLedger::balance((int) $bank->id));
        $this->assertSame(KitchenWithdrawalRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame(PartnerPayable::STATUS_SETTLED, PartnerPayable::query()->first()->status);
    }

    public function test_kitchen_can_submit_transfer_and_ops_confirms(): void
    {
        // Simulate surplus cash already held (kitchen owes Middo).
        KitchenAccountLedger::debit($this->kitchen->id, 120, 'cash_received', null, null, 'Seed surplus', $this->admin->id);
        $this->assertSame(-120, KitchenAccountLedger::balance($this->kitchen->id));

        Livewire::actingAs($this->kitchen)
            ->test(Account::class)
            ->set('transferAmount', 120)
            ->set('transferReference', 'BKASH-1')
            ->set('transferProof', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitTransfer')
            ->assertSet('errorMessage', '');

        $transfer = KitchenMiddoTransfer::query()->first();
        $this->assertNotNull($transfer);
        $this->assertSame(120, (int) $transfer->amount);
        $this->assertNotNull($transfer->proof_path);
        $this->assertFileExists(public_path($transfer->proof_path));

        Livewire::actingAs($this->admin)
            ->test(KitchenMoneyApprovals::class)
            ->set('tab', 'transfers')
            ->call('confirmTransfer', $transfer->id)
            ->assertSet('errorMessage', '');

        $this->assertSame(120, MiddoCashLedger::balance());
        $this->assertSame(0, KitchenAccountLedger::balance($this->kitchen->id));
        $this->assertSame(KitchenMiddoTransfer::STATUS_CONFIRMED, $transfer->fresh()->status);
    }

    public function test_cash_handover_is_scoped_to_kitchen_orders(): void
    {
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'One',
            'mobile' => '01740000009',
            'password' => 'password',
            'role_id' => Role::where('name', 'delivery')->value('id'),
            'status' => 'active',
            'balance' => 500,
        ]);

        $ownOrder = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'cash_collected' => 100,
            'address' => 'A',
            'order_status' => 'delivered',
            'payment_status' => 'pending',
        ]);
        OrderGroup::create([
            'name' => 'GRP-OWN',
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $this->kitchen->id,
        ])->orders()->attach($ownOrder->id);

        $otherOrder = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'cash_collected' => 80,
            'address' => 'B',
            'order_status' => 'delivered',
            'payment_status' => 'pending',
        ]);
        OrderGroup::create([
            'name' => 'GRP-OTHER',
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $this->otherKitchen->id,
        ])->orders()->attach($otherOrder->id);

        $ownHandover = CashHandover::create(['rider_id' => $rider->id, 'amount' => 100, 'status' => 'pending']);
        CashHandoverOrder::create(['cash_handover_id' => $ownHandover->id, 'order_id' => $ownOrder->id, 'amount' => 100]);

        $otherHandover = CashHandover::create(['rider_id' => $rider->id, 'amount' => 80, 'status' => 'pending']);
        CashHandoverOrder::create(['cash_handover_id' => $otherHandover->id, 'order_id' => $otherOrder->id, 'amount' => 80]);

        Livewire::actingAs($this->kitchen)
            ->test(CashHandovers::class)
            ->assertSee('#'.$ownHandover->id)
            ->assertDontSee('#'.$otherHandover->id)
            ->call('accept', $otherHandover->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'not linked'));

        Livewire::actingAs($this->kitchen)
            ->test(CashHandovers::class)
            ->call('accept', $ownHandover->id)
            ->assertSet('errorMessage', null);

        $this->assertSame(0, MiddoCashLedger::balance());
        $this->assertSame(-100, KitchenAccountLedger::balance($this->kitchen->id));
    }
}
