<?php

namespace Tests\Feature\Accounts;

use App\Models\KitchenSettlementBatch;
use App\Models\KitchenWithdrawalRequest;
use App\Models\MiddoBankAccount;
use App\Models\RiderWithdrawalRequest;
use App\Models\User;
use App\Support\MiddoBankLedger;
use App\Support\MiddoCashLedger;
use App\Support\MiddoSettings;
use App\Support\PayoutChannel;
use Database\Seeders\AccountsFinanceTestSeeder;
use Database\Seeders\AreaSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\MenuItemSeeder;
use Database\Seeders\NavSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsFinanceTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_finance_seeder_builds_demo_fixtures(): void
    {
        $this->seed([
            CitySeeder::class,
            AreaSeeder::class,
            RolePermissionSeeder::class,
            NavSeeder::class,
            UserSeeder::class,
            MenuItemSeeder::class,
            AccountsFinanceTestSeeder::class,
        ]);

        $this->assertEqualsWithDelta(5.0, MiddoSettings::vatRatePct(), 0.001);
        $this->assertDatabaseHas('middo_bank_accounts', [
            'name' => 'Ops BRAC',
            'is_default' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('middo_bank_accounts', [
            'name' => 'Ops City',
            'is_active' => true,
        ]);

        $bank = MiddoBankAccount::query()->where('name', 'Ops BRAC')->first();
        $this->assertNotNull($bank);
        $this->assertGreaterThan(0, MiddoBankLedger::balance((int) $bank->id));
        $this->assertGreaterThanOrEqual(5000, MiddoCashLedger::balance());

        $this->assertDatabaseHas('orders', [
            'address' => 'AccountsFinance P&L demo',
        ]);
        $this->assertDatabaseHas('middo_operating_costs', [
            'run_type' => 'box',
        ]);

        $kitchen = User::query()->where('email', 'kitchen@middo.com')->first();
        $this->assertNotNull($kitchen);
        $this->assertDatabaseHas('kitchen_withdrawal_requests', [
            'kitchen_user_id' => $kitchen->id,
            'status' => KitchenWithdrawalRequest::STATUS_PENDING,
            'payout_channel' => PayoutChannel::BANK,
        ]);

        $rider = User::query()->where('email', 'delivery@middo.com')->first();
        $this->assertNotNull($rider);
        $this->assertDatabaseHas('rider_withdrawal_requests', [
            'rider_user_id' => $rider->id,
            'status' => RiderWithdrawalRequest::STATUS_PENDING,
            'payout_channel' => PayoutChannel::BKASH,
        ]);

        $this->assertDatabaseHas('kitchen_settlement_batches', [
            'name' => 'Demo week remittance',
            'status' => KitchenSettlementBatch::STATUS_PENDING,
            'kitchen_user_id' => $kitchen->id,
        ]);

        $this->assertDatabaseHas('navs', [
            'route_name' => 'accounts.period-pnl',
        ]);
    }
}
