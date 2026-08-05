<?php

namespace App\Support;

use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F0c cash position buckets for accounts.
 *
 * cash_at_eps: not modeled as float (EPS settles to bank on callback) — always 0 with note.
 * cash_receivable_kitchen: sum of kitchen ledger debts (negative balances).
 * cash_receivable_riders: sum of delivery users.balance (Due float).
 * cash_in_hand: MiddoCashLedger till.
 */
class CashPositions
{
    /**
     * @return array{
     *   cash_at_eps: array{amount:int, note:string, actionable:bool},
     *   cash_receivable_kitchen: array{amount:int, note:string, actionable:bool, kitchens:list<array{id:int,name:string,amount:int}>},
     *   cash_receivable_riders: array{amount:int, note:string, actionable:bool},
     *   cash_in_hand: array{amount:int, note:string, actionable:bool},
     *   bank_float: array{amount:int, note:string, accounts:list<array{id:int,label:string,amount:int}>},
     *   total_cash_cycle: int
     * }
     */
    public static function snapshot(): array
    {
        $kitchen = self::kitchenReceivable();
        $riders = CodDueRecon::riderFloatTotal();
        $till = MiddoCashLedger::balance();
        $bank = self::bankFloat();

        return [
            'cash_at_eps' => [
                'amount' => 0,
                'note' => 'EPS settles to bank on success (F0b). Unpaid checkout sessions are not Middo float.',
                'actionable' => false,
            ],
            'cash_receivable_kitchen' => [
                'amount' => $kitchen['total'],
                'note' => 'Kitchen owes Middo (cash received from riders net of unpaid kitchen share). Confirm kitchen→Middo transfers to clear.',
                'actionable' => $kitchen['total'] > 0,
                'kitchens' => $kitchen['rows'],
            ],
            'cash_receivable_riders' => [
                'amount' => $riders,
                'note' => 'Rider Due float (users.balance) until Middo cash handover is accepted.',
                'actionable' => $riders > 0,
            ],
            'cash_in_hand' => [
                'amount' => $till,
                'note' => 'MiddoCashLedger till (cash SoT). Deposit to bank below.',
                'actionable' => $till > 0,
            ],
            'bank_float' => [
                'amount' => $bank['total'],
                'note' => 'Middo bank ledger (separate SoT). Includes EPS net credits.',
                'accounts' => $bank['rows'],
            ],
            'total_cash_cycle' => $kitchen['total'] + $riders + $till,
        ];
    }

    /**
     * @return array{total:int, rows:list<array{id:int,name:string,amount:int}>}
     */
    public static function kitchenReceivable(): array
    {
        if (! Schema::hasTable('kitchen_account_ledger') || ! Schema::hasTable('users')) {
            return ['total' => 0, 'rows' => []];
        }

        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        if (! $kitchenRoleId) {
            return ['total' => 0, 'rows' => []];
        }

        $rows = [];
        $total = 0;

        User::query()
            ->where('role_id', $kitchenRoleId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->each(function (User $kitchen) use (&$rows, &$total) {
                $balance = KitchenAccountLedger::balance((int) $kitchen->id);
                $owes = max(0, -$balance);
                if ($owes < 1) {
                    return;
                }
                $total += $owes;
                $rows[] = [
                    'id' => (int) $kitchen->id,
                    'name' => $kitchen->name,
                    'amount' => $owes,
                ];
            });

        usort($rows, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * @return array{total:int, rows:list<array{id:int,label:string,amount:int}>}
     */
    public static function bankFloat(): array
    {
        if (! Schema::hasTable('middo_bank_accounts')) {
            return ['total' => 0, 'rows' => []];
        }

        $rows = MiddoBankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (MiddoBankAccount $a) => [
                'id' => (int) $a->id,
                'label' => $a->label(),
                'amount' => MiddoBankLedger::balance((int) $a->id),
            ])
            ->all();

        return [
            'total' => (int) collect($rows)->sum('amount'),
            'rows' => $rows,
        ];
    }

    /**
     * Double-entry: debit Middo cash till, credit a Middo bank account.
     */
    public static function depositTillToBank(
        int $bankAccountId,
        int $amount,
        string $reason,
        ?int $actorId = null,
    ): void {
        if ($amount < 1) {
            throw new \InvalidArgumentException('Deposit amount must be positive.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required for till→bank deposits.');
        }

        $account = MiddoBankAccount::query()
            ->whereKey($bankAccountId)
            ->where('is_active', true)
            ->first();
        if (! $account) {
            throw new \RuntimeException('Bank account not found or inactive.');
        }

        $till = MiddoCashLedger::balance();
        if ($amount > $till) {
            throw new \RuntimeException('Deposit exceeds cash in hand (৳'.number_format($till).').');
        }

        DB::transaction(function () use ($account, $amount, $reason, $actorId) {
            MiddoCashLedger::debit(
                $amount,
                'till_to_bank',
                MiddoBankAccount::class,
                (int) $account->id,
                'Till→bank: '.$reason.' → '.$account->label(),
                $actorId
            );

            MiddoBankLedger::credit(
                (int) $account->id,
                $amount,
                MiddoBankLedgerEntry::TYPE_TRANSFER,
                null,
                null,
                'Till→bank: '.$reason,
                $actorId
            );
        });
    }
}
