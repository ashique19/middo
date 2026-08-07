<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Kitchen\Account as KitchenAccount;
use App\Livewire\Shared\KitchenMoneyApprovals;
use App\Models\KitchenWithdrawalRequest;
use App\Models\MenuItem;
use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\MiddoBankLedger;
use App\Support\MiddoCashLedger;
use App\Support\PayoutChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class WithdrawalChannelsF0dTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $admin;

    protected MenuItem $menu;

    protected User $corporate;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $adminRole = Role::create(['name' => 'admin']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01751000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01751000002',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01751000003',
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
            'name' => 'GRP-F0D-'.uniqid(),
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

    public function test_bank_withdrawal_debits_bank_not_cash(): void
    {
        $this->accrueKitchenShare(50);
        MiddoCashLedger::credit(500, 'seed', null, null, 'Seed till', $this->admin->id);

        $bank = MiddoBankAccount::create([
            'name' => 'Ops BRAC',
            'bank_name' => 'BRAC',
            'is_default' => true,
            'is_active' => true,
        ]);
        MiddoBankLedger::credit((int) $bank->id, 1000, MiddoBankLedgerEntry::TYPE_ADJUSTMENT, null, null, 'Seed bank', $this->admin->id);

        $this->kitchen->storePayoutMethods([
            'preferred' => PayoutChannel::BANK,
            PayoutChannel::BANK => [
                'bank_name' => 'Dutch Bangla',
                'account_name' => 'Kitchen Owner',
                'account_number' => '1234567890',
            ],
        ]);
        $this->kitchen->save();

        Livewire::actingAs($this->kitchen)
            ->test(KitchenAccount::class)
            ->set('withdrawAmount', 50)
            ->set('payoutChannel', PayoutChannel::BANK)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', '');

        $request = KitchenWithdrawalRequest::query()->firstOrFail();
        $this->assertSame(PayoutChannel::BANK, $request->payout_channel);
        $this->assertSame('1234567890', $request->payout_details['account_number'] ?? null);

        Livewire::actingAs($this->admin)
            ->test(KitchenMoneyApprovals::class)
            ->set("approveBankAccountId.{$request->id}", $bank->id)
            ->set("approveAttachment.{$request->id}", UploadedFile::fake()->image('proof.jpg'))
            ->call('approveWithdrawal', $request->id)
            ->assertSet('errorMessage', '');

        $request->refresh();
        $this->assertSame(KitchenWithdrawalRequest::STATUS_APPROVED, $request->status);
        $this->assertSame(500, MiddoCashLedger::balance());
        $this->assertSame(950, MiddoBankLedger::balance((int) $bank->id));
        $this->assertSame(0, KitchenAccountLedger::balance($this->kitchen->id));
        $this->assertNotNull($request->middo_bank_ledger_entry_id);
        $this->assertNull($request->middo_cash_ledger_entry_id);
        $this->assertNotNull($request->attachment_path);
        $this->assertFileExists(public_path($request->attachment_path));
    }

    public function test_cash_withdrawal_still_debits_till(): void
    {
        $this->accrueKitchenShare(50);
        MiddoCashLedger::credit(500, 'seed', null, null, 'Seed till', $this->admin->id);

        Livewire::actingAs($this->kitchen)
            ->test(KitchenAccount::class)
            ->set('withdrawAmount', 50)
            ->set('payoutChannel', PayoutChannel::CASH)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', '');

        $request = KitchenWithdrawalRequest::query()->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(KitchenMoneyApprovals::class)
            ->call('approveWithdrawal', $request->id)
            ->assertSet('errorMessage', '');

        $this->assertSame(450, MiddoCashLedger::balance());
        $this->assertNotNull($request->fresh()->middo_cash_ledger_entry_id);
        $this->assertNull($request->fresh()->middo_bank_ledger_entry_id);
    }

    public function test_bank_approve_requires_account_selection(): void
    {
        $this->accrueKitchenShare(50);
        MiddoCashLedger::credit(500, 'seed', null, null, 'Seed till', $this->admin->id);

        MiddoBankAccount::create([
            'name' => 'Ops BRAC',
            'bank_name' => 'BRAC',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->kitchen->storePayoutMethods([
            'preferred' => PayoutChannel::BKASH,
            PayoutChannel::BKASH => [
                'mobile' => '01711111111',
            ],
        ]);
        $this->kitchen->save();

        Livewire::actingAs($this->kitchen)
            ->test(KitchenAccount::class)
            ->set('withdrawAmount', 50)
            ->set('payoutChannel', PayoutChannel::BKASH)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', '');

        $request = KitchenWithdrawalRequest::query()->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(KitchenMoneyApprovals::class)
            ->call('approveWithdrawal', $request->id)
            ->assertHasErrors(["approveBankAccountId.{$request->id}"]);

        $this->assertSame(KitchenWithdrawalRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertSame(500, MiddoCashLedger::balance());
    }

    public function test_bank_withdrawal_requires_profile_details(): void
    {
        $this->accrueKitchenShare(50);

        Livewire::actingAs($this->kitchen)
            ->test(KitchenAccount::class)
            ->set('withdrawAmount', 50)
            ->set('payoutChannel', PayoutChannel::BANK)
            ->call('requestWithdrawal')
            ->assertSet('errorMessage', 'Add your Bank details in profile before requesting this payout.');

        $this->assertSame(0, KitchenWithdrawalRequest::query()->count());
    }
}
