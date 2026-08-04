<?php

namespace App\Support;

use App\Models\KitchenAccountLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KitchenAccountLedger
{
    public static function balance(int $kitchenUserId): int
    {
        if (! Schema::hasTable('kitchen_account_ledger')) {
            return 0;
        }

        return (int) (KitchenAccountLedgerEntry::query()
            ->where('kitchen_user_id', $kitchenUserId)
            ->latest('id')
            ->value('balance_after') ?? 0);
    }

    public static function credit(
        int $kitchenUserId,
        int $amount,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
    ): KitchenAccountLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return self::write($kitchenUserId, $amount, $entryType, $referenceType, $referenceId, $description, $createdBy);
    }

    public static function debit(
        int $kitchenUserId,
        int $amount,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
    ): KitchenAccountLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return self::write($kitchenUserId, -$amount, $entryType, $referenceType, $referenceId, $description, $createdBy);
    }

    protected static function write(
        int $kitchenUserId,
        int $signedAmount,
        string $entryType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $description,
        ?int $createdBy,
    ): KitchenAccountLedgerEntry {
        if (! Schema::hasTable('kitchen_account_ledger')) {
            throw new \RuntimeException('Kitchen account ledger is not available.');
        }

        return DB::transaction(function () use ($kitchenUserId, $signedAmount, $entryType, $referenceType, $referenceId, $description, $createdBy) {
            $current = (int) (KitchenAccountLedgerEntry::query()
                ->where('kitchen_user_id', $kitchenUserId)
                ->lockForUpdate()
                ->latest('id')
                ->value('balance_after') ?? 0);

            // Signed balance: positive = Middo owes kitchen; negative = kitchen owes Middo
            // (e.g. cash received from riders exceeds dispatched kitchen share).
            $next = $current + $signedAmount;

            return KitchenAccountLedgerEntry::query()->create([
                'kitchen_user_id' => $kitchenUserId,
                'amount' => $signedAmount,
                'balance_after' => $next,
                'entry_type' => $entryType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_by' => $createdBy,
            ]);
        });
    }
}
