<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Shared\KitchenMoneyApprovals;
use App\Models\KitchenSettlementBatch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\PartnerPayable;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\KitchenMoneyService;
use App\Support\MiddoCashLedger;
use App\Support\PayoutChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenSettlementBatchesF2Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $admin;

    protected User $corporate;

    protected MenuItem $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $adminRole = Role::create(['name' => 'admin']);
        $corporateRole = Role::create(['name' => 'corporate']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01752000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '01752000002',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01752000003',
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

    protected function accruePayable(): PartnerPayable
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
            'name' => 'GRP-F2-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $this->kitchen->id,
        ]);
        $group->orders()->attach($order->id);

        $order->update([
            'dispatched_at' => now(),
            'order_status' => 'delivered_and_paid',
        ]);

        return PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
            ->firstOrFail();
    }

    public function test_create_and_approve_settlement_batch(): void
    {
        $p1 = $this->accruePayable();
        $p2 = $this->accruePayable();
        MiddoCashLedger::credit(500, 'seed', null, null, 'Seed', $this->admin->id);

        $this->assertSame(100, KitchenAccountLedger::balance($this->kitchen->id));

        Livewire::actingAs($this->admin)
            ->test(KitchenMoneyApprovals::class)
            ->set('tab', 'batches')
            ->set('batchKitchenId', $this->kitchen->id)
            ->set('batchName', 'Week remittance')
            ->set('batchPayoutChannel', PayoutChannel::CASH)
            ->set('batchPayableIds', [$p1->id, $p2->id])
            ->call('createBatch')
            ->assertSet('errorMessage', '');

        $batch = KitchenSettlementBatch::query()->firstOrFail();
        $this->assertSame(100, (int) $batch->amount);
        $this->assertSame(2, $batch->items()->count());
        $this->assertContains($p1->id, KitchenMoneyService::reservedPayableIds($this->kitchen->id));

        Livewire::actingAs($this->admin)
            ->test(KitchenMoneyApprovals::class)
            ->set('tab', 'batches')
            ->call('approveBatch', $batch->id)
            ->assertSet('errorMessage', '');

        $this->assertSame(KitchenSettlementBatch::STATUS_APPROVED, $batch->fresh()->status);
        $this->assertSame(0, KitchenAccountLedger::balance($this->kitchen->id));
        $this->assertSame(400, MiddoCashLedger::balance());
        $this->assertSame(PartnerPayable::STATUS_SETTLED, $p1->fresh()->status);
        $this->assertSame(PartnerPayable::STATUS_SETTLED, $p2->fresh()->status);
        $this->assertSame([], KitchenMoneyService::reservedPayableIds($this->kitchen->id));
    }

    public function test_reject_releases_payables(): void
    {
        $p1 = $this->accruePayable();

        $batch = KitchenMoneyService::createSettlementBatch(
            $this->kitchen->id,
            'Temp',
            [$p1->id],
            PayoutChannel::CASH,
            [],
            $this->admin->id,
        );

        KitchenMoneyService::rejectSettlementBatch($batch, $this->admin->id, 'Nope');

        $this->assertSame(KitchenSettlementBatch::STATUS_REJECTED, $batch->fresh()->status);
        $this->assertSame(0, $batch->items()->count());
        $this->assertSame(PartnerPayable::STATUS_OPEN, $p1->fresh()->status);
        $this->assertSame([], KitchenMoneyService::reservedPayableIds($this->kitchen->id));
    }

    public function test_pending_batch_blocks_second_reservation(): void
    {
        $p1 = $this->accruePayable();

        KitchenMoneyService::createSettlementBatch(
            $this->kitchen->id,
            'First',
            [$p1->id],
            PayoutChannel::CASH,
            [],
            $this->admin->id,
        );

        $this->expectException(\RuntimeException::class);
        KitchenMoneyService::createSettlementBatch(
            $this->kitchen->id,
            'Second',
            [$p1->id],
            PayoutChannel::CASH,
            [],
            $this->admin->id,
        );
    }
}
