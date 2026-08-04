<?php

namespace Tests\Feature\Ops;

use App\Livewire\Shared\AccountsHub;
use App\Livewire\Shared\CodDueReconPage;
use App\Livewire\Shared\MiddoCashLedgerPage;
use App\Livewire\Shared\OperatingCostsPage;
use App\Models\CashHandover;
use App\Models\CashHandoverOrder;
use App\Models\MenuItem;
use App\Models\MiddoOperatingCost;
use App\Models\Order;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\CodDueRecon;
use App\Support\DeliveryRunType;
use App\Support\MiddoCashLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpsMoneyO3Test extends TestCase
{
    use RefreshDatabase;

    protected User $ops;

    protected User $admin;

    protected User $rider;

    protected User $kitchen;

    protected User $corporate;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::create(['name' => 'operation']);
        $adminRole = Role::create(['name' => 'admin']);
        $deliveryRole = Role::create(['name' => 'delivery']);
        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->ops = User::create([
            'first_name' => 'Ops', 'last_name' => 'O3', 'mobile' => '01960000001',
            'password' => 'password', 'role_id' => $opsRole->id, 'status' => 'active',
        ]);
        $this->admin = User::create([
            'first_name' => 'Admin', 'last_name' => 'O3', 'mobile' => '01960000002',
            'password' => 'password', 'role_id' => $adminRole->id, 'status' => 'active',
        ]);
        $this->rider = User::create([
            'first_name' => 'Rider', 'last_name' => 'O3', 'mobile' => '01960000003',
            'password' => 'password', 'role_id' => $deliveryRole->id, 'status' => 'active', 'balance' => 0,
        ]);
        $this->kitchen = User::create([
            'first_name' => 'Kitchen', 'last_name' => 'O3', 'mobile' => '01960000004',
            'password' => 'password', 'role_id' => $kitchenRole->id, 'status' => 'active',
        ]);
        $this->corporate = User::create([
            'first_name' => 'Corp', 'last_name' => 'O3', 'mobile' => '01960000005',
            'password' => 'password', 'role_id' => $corporateRole->id, 'status' => 'active',
        ]);
        $this->menu = MenuItem::create([
            'name' => 'O3 Thali',
            'price' => 200,
            'kitchen_commission' => 50,
            'delivery_commission' => 40,
        ]);
    }

    public function test_cod_recon_aggregates_day_by_rider(): void
    {
        $date = '2026-08-05';
        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => $date,
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'HQ',
            'order_status' => 'delivered_and_paid',
            'payment_status' => 'paid',
            'delivery_rider_id' => $this->rider->id,
            'cash_collected' => 200,
            'cash_due_to_middo' => 160,
        ]);

        $handover = CashHandover::create([
            'rider_id' => $this->rider->id,
            'amount' => 160,
            'target' => CashHandover::TARGET_MIDDO,
            'status' => 'accepted',
        ]);
        CashHandoverOrder::create([
            'cash_handover_id' => $handover->id,
            'order_id' => $order->id,
            'amount' => 160,
        ]);

        $report = CodDueRecon::forDate($date);
        $this->assertCount(1, $report['rows']);
        $this->assertSame(200, $report['rows'][0]['collected']);
        $this->assertSame(40, $report['rows'][0]['commission']);
        $this->assertSame(160, $report['rows'][0]['due']);
        $this->assertSame(160, $report['rows'][0]['accepted']);
        $this->assertSame(0, $report['rows'][0]['variance']);

        Livewire::actingAs($this->ops)
            ->test(CodDueReconPage::class)
            ->set('date', $date)
            ->assertSee('Rider O3')
            ->assertSee('৳160');

        $this->actingAs($this->ops)->get(route('operation.cod-recon.index'))->assertOk();
    }

    public function test_operating_costs_page_lists_booked_costs(): void
    {
        MiddoOperatingCost::create([
            'cost_type' => MiddoOperatingCost::TYPE_RIDER_COMMISSION,
            'amount' => 25,
            'run_type' => DeliveryRunType::OPS_TO_KITCHEN,
            'rider_user_id' => $this->rider->id,
            'description' => 'Box run test',
            'created_by' => $this->ops->id,
        ]);

        Livewire::actingAs($this->ops)
            ->test(OperatingCostsPage::class)
            ->assertSee('Operating costs')
            ->assertSee('৳25')
            ->assertSee('ops to kitchen');

        $this->actingAs($this->admin)->get(route('admin.operating-costs.index'))->assertOk();
    }

    public function test_accounts_hub_blocks_kitchen_settle(): void
    {
        $payable = PartnerPayable::create([
            'order_id' => Order::create([
                'user_id' => $this->corporate->id,
                'menu_item_id' => $this->menu->id,
                'quantity' => 1,
                'delivery_date' => now('Asia/Dhaka')->toDateString(),
                'delivery_time' => '12:00 PM',
                'total_amount' => 200,
                'address' => 'HQ',
                'order_status' => 'processing',
                'payment_status' => 'pending',
            ])->id,
            'beneficiary_role' => PartnerPayable::ROLE_KITCHEN,
            'beneficiary_user_id' => $this->kitchen->id,
            'amount' => 50,
            'status' => PartnerPayable::STATUS_OPEN,
        ]);

        Livewire::actingAs($this->ops)
            ->test(AccountsHub::class)
            ->call('settlePayable', $payable->id)
            ->assertSet('errorMessage', fn ($m) => is_string($m) && str_contains($m, 'Kitchen money'));

        $this->assertSame(PartnerPayable::STATUS_OPEN, $payable->fresh()->status);
        Livewire::actingAs($this->ops)
            ->test(AccountsHub::class)
            ->assertSee('pay via Kitchen money');
    }

    public function test_middo_cash_adjustment_accounts_only_ops_can_view_variance(): void
    {
        $accountsRole = Role::firstOrCreate(['name' => 'accounts']);
        $accounts = User::create([
            'first_name' => 'Accounts', 'last_name' => 'O3', 'mobile' => '01950000999',
            'password' => 'password', 'role_id' => $accountsRole->id, 'status' => 'active',
        ]);

        MiddoCashLedger::credit(500, 'seed', null, null, 'Seed', $this->ops->id);

        Livewire::actingAs($this->ops)
            ->test(MiddoCashLedgerPage::class)
            ->set('adjustDirection', 'debit')
            ->set('adjustAmount', '50')
            ->set('adjustReason', 'Day-end count correction')
            ->call('postAdjustment')
            ->assertForbidden();

        $this->assertSame(500, MiddoCashLedger::balance());

        Livewire::actingAs($accounts)
            ->test(MiddoCashLedgerPage::class)
            ->set('adjustDirection', 'debit')
            ->set('adjustAmount', '50')
            ->set('adjustReason', 'Day-end count correction')
            ->call('postAdjustment')
            ->assertSet('errorMessage', '')
            ->assertSet('statusMessage', fn ($m) => str_contains((string) $m, 'Debit'));

        $this->assertSame(450, MiddoCashLedger::balance());

        Livewire::actingAs($this->ops)
            ->test(MiddoCashLedgerPage::class)
            ->set('countedCash', '440')
            ->assertSee('৳450')
            ->assertSee('৳-10');
    }
}
