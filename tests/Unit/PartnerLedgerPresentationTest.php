<?php

namespace Tests\Unit;

use App\Models\CashHandover;
use App\Models\KitchenAccountLedgerEntry;
use App\Models\RiderAccountLedgerEntry;
use App\Models\Role;
use App\Models\User;
use App\Support\PartnerLedgerPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerLedgerPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_cash_received_summarizes_from_rider_name(): void
    {
        $deliveryRole = Role::create(['name' => 'delivery']);
        $rider = User::create([
            'first_name' => 'Karim',
            'last_name' => 'Ali',
            'mobile' => '01993000001',
            'password' => '12345678',
            'role_id' => $deliveryRole->id,
            'status' => 'active',
        ]);

        $handover = CashHandover::create([
            'rider_id' => $rider->id,
            'amount' => 500,
            'status' => 'accepted',
            'target' => CashHandover::TARGET_KITCHEN,
        ]);

        $entry = new KitchenAccountLedgerEntry([
            'kitchen_user_id' => 1,
            'amount' => -500,
            'balance_after' => -500,
            'entry_type' => 'cash_received',
            'reference_type' => CashHandover::class,
            'reference_id' => $handover->id,
            'description' => "Cash handover #{$handover->id}",
        ]);
        $entry->id = 11;
        $entry->created_at = now();

        $row = PartnerLedgerPresentation::presentKitchenEntries(collect([$entry]))->first();

        $this->assertSame(PartnerLedgerPresentation::FILTER_CASH_IN, $row->direction);
        $this->assertSame('From rider Karim Ali', $row->summary);
        $this->assertSame(500, $row->amount);
    }

    public function test_kitchen_withdrawal_request_is_cash_out(): void
    {
        $entry = new KitchenAccountLedgerEntry([
            'kitchen_user_id' => 1,
            'amount' => -200,
            'balance_after' => 50,
            'entry_type' => 'withdrawal_requested',
            'description' => 'Withdrawal request',
        ]);
        $entry->id = 12;
        $entry->created_at = now();

        $row = PartnerLedgerPresentation::presentKitchenEntries(collect([$entry]))->first();

        $this->assertSame(PartnerLedgerPresentation::FILTER_CASH_OUT, $row->direction);
        $this->assertSame('Withdraw req', $row->summary);
    }

    public function test_rider_commission_and_withdraw_summaries(): void
    {
        $credit = new RiderAccountLedgerEntry([
            'rider_user_id' => 1,
            'amount' => 40,
            'balance_after' => 40,
            'entry_type' => 'commission_accrued',
            'description' => 'Commission for order #77',
        ]);
        $credit->id = 21;
        $credit->created_at = now();

        $debit = new RiderAccountLedgerEntry([
            'rider_user_id' => 1,
            'amount' => -40,
            'balance_after' => 0,
            'entry_type' => 'withdrawal_requested',
            'description' => 'Withdrawal',
        ]);
        $debit->id = 22;
        $debit->created_at = now();

        $rows = PartnerLedgerPresentation::presentRiderEntries(collect([$credit, $debit]));

        $this->assertSame(PartnerLedgerPresentation::FILTER_CASH_IN, $rows[0]->direction);
        $this->assertSame('Commission #77', $rows[0]->summary);
        $this->assertSame(PartnerLedgerPresentation::FILTER_CASH_OUT, $rows[1]->direction);
        $this->assertSame('Withdraw req', $rows[1]->summary);
    }
}
