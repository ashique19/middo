<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Shared\CashPositionsBoard;
use App\Models\MiddoBankAccount;
use App\Models\Role;
use App\Models\User;
use App\Support\CashPositions;
use App\Support\KitchenAccountLedger;
use App\Support\MiddoBankLedger;
use App\Support\MiddoCashLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashPositionsF0cTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_sums_kitchen_rider_and_till(): void
    {
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        Role::create(['name' => 'accounts']);

        $kitchen = User::create([
            'first_name' => 'Kit',
            'last_name' => 'One',
            'mobile' => '01891000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => 'Due',
            'mobile' => '01891000002',
            'password' => 'password',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
            'balance' => 350,
        ]);

        // Kitchen owes Middo 200 (debit cash_received style)
        KitchenAccountLedger::debit($kitchen->id, 200, 'cash_received', null, null, 'test');
        MiddoCashLedger::credit(500, 'seed', null, null, 'till seed');

        $snap = CashPositions::snapshot();
        $this->assertSame(0, $snap['cash_at_eps']['amount']);
        $this->assertSame(200, $snap['cash_receivable_kitchen']['amount']);
        $this->assertSame(350, $snap['cash_receivable_riders']['amount']);
        $this->assertSame(500, $snap['cash_in_hand']['amount']);
        $this->assertSame(1050, $snap['total_cash_cycle']);
        $this->assertCount(1, $snap['cash_receivable_kitchen']['kitchens']);
    }

    public function test_accounts_can_deposit_till_to_bank(): void
    {
        $accountsRole = Role::create(['name' => 'accounts']);
        $accounts = User::create([
            'first_name' => 'Acc',
            'last_name' => 'User',
            'mobile' => '01891000003',
            'password' => 'password',
            'role_id' => $accountsRole->id,
            'status' => 'active',
        ]);

        $bank = MiddoBankAccount::create([
            'name' => 'Ops BRAC',
            'bank_name' => 'BRAC',
            'is_default' => true,
            'is_active' => true,
        ]);

        MiddoCashLedger::credit(1000, 'seed', null, null, 'till');

        Livewire::actingAs($accounts)
            ->test(CashPositionsBoard::class)
            ->assertSee('Cash positions', false)
            ->assertSee('৳1,000', false)
            ->set('depositBankAccountId', $bank->id)
            ->set('depositAmount', '400')
            ->set('depositReason', 'Branch slip 12')
            ->call('depositTillToBank')
            ->assertSet('errorMessage', '');

        $this->assertSame(600, MiddoCashLedger::balance());
        $this->assertSame(400, MiddoBankLedger::balance((int) $bank->id));
    }
}
