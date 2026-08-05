<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Shared\PeriodPnlPage;
use App\Models\MenuItem;
use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use App\Models\MiddoOperatingCost;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\MiddoBankLedger;
use App\Support\MiddoSettings;
use App\Support\PeriodPnl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PeriodPnlF0eTest extends TestCase
{
    use RefreshDatabase;

    protected User $accounts;

    protected User $corporate;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $accountsRole = Role::create(['name' => 'accounts']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'admin']);

        $this->accounts = User::create([
            'first_name' => 'Acc',
            'last_name' => 'Pnl',
            'mobile' => '01892000001',
            'password' => 'password',
            'role_id' => $accountsRole->id,
            'status' => 'active',
        ]);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Pnl',
            'mobile' => '01892000002',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'P&L Thali',
            'price' => 400,
            'kitchen_commission' => 100,
            'delivery_commission' => 50,
        ]);

        MiddoSettings::set(MiddoSettings::KEY_VAT_RATE_PCT, '5');
    }

    public function test_period_pnl_rolls_up_orders_opex_and_eps_fees(): void
    {
        $today = now('Asia/Dhaka')->toDateString();

        Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'address' => 'Office',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);

        MiddoOperatingCost::create([
            'cost_type' => MiddoOperatingCost::TYPE_RIDER_COMMISSION,
            'amount' => 30,
            'run_type' => 'box',
            'description' => 'Box commission',
            'created_by' => $this->accounts->id,
        ]);

        $bank = MiddoBankAccount::create([
            'name' => 'Ops',
            'bank_name' => 'BRAC',
            'is_default' => true,
            'is_active' => true,
        ]);
        MiddoBankLedger::credit(
            (int) $bank->id,
            982,
            MiddoBankLedgerEntry::TYPE_EPS_IN_NET,
            null,
            null,
            'EPS net',
            $this->accounts->id,
            [
                'gross_amount' => 1000,
                'fee_amount' => 18,
                'sub_gateway' => 'bank',
            ]
        );

        $report = PeriodPnl::forRange($today, $today);
        $byKey = collect($report['lines'])->keyBy('key');

        $this->assertSame(1, $report['order_count']);
        $this->assertSame(381, $byKey['food_ex_vat']['amount']); // 400 - 19
        $this->assertSame(19, $byKey['vat']['amount']);
        $this->assertSame(-100, $byKey['kitchen_share']['amount']);
        $this->assertSame(-50, $byKey['delivery_share']['amount']);
        $this->assertSame(231, $byKey['middo_rest']['amount']);
        $this->assertSame(-30, $byKey['operating_costs']['amount']);
        $this->assertSame(-18, $byKey['eps_fees']['amount']);
        $this->assertSame(183, $byKey['contribution']['amount']); // 231 - 30 - 18
        $this->assertNotEmpty($report['bank_by_type']);
    }

    public function test_accounts_can_view_and_export_period_pnl(): void
    {
        $this->actingAs($this->accounts)
            ->get(route('accounts.period-pnl'))
            ->assertOk()
            ->assertSee('Period P&L');

        Livewire::actingAs($this->accounts)
            ->test(PeriodPnlPage::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_cancelled_orders_excluded(): void
    {
        $today = now('Asia/Dhaka')->toDateString();

        Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $today,
            'delivery_time' => '12:00 PM',
            'total_amount' => 400,
            'address' => 'Office',
            'order_status' => 'cancelled',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);

        $report = PeriodPnl::forRange($today, $today);
        $this->assertSame(0, $report['order_count']);
        $this->assertSame(0, collect($report['lines'])->firstWhere('key', 'middo_rest')['amount']);
    }
}
