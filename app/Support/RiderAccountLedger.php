<?php

namespace App\Support;

use App\Models\RiderAccountLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiderAccountLedger
{
    public static function balance(int $riderUserId): int
    {
        if (! Schema::hasTable('rider_account_ledger')) {
            return 0;
        }

        return (int) (RiderAccountLedgerEntry::query()
            ->where('rider_user_id', $riderUserId)
            ->latest('id')
            ->value('balance_after') ?? 0);
    }

    public static function credit(
        int $riderUserId,
        int $amount,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
    ): RiderAccountLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return self::write($riderUserId, $amount, $entryType, $referenceType, $referenceId, $description, $createdBy);
    }

    public static function debit(
        int $riderUserId,
        int $amount,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
    ): RiderAccountLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return self::write($riderUserId, -$amount, $entryType, $referenceType, $referenceId, $description, $createdBy);
    }

    protected static function write(
        int $riderUserId,
        int $signedAmount,
        string $entryType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $description,
        ?int $createdBy,
    ): RiderAccountLedgerEntry {
        if (! Schema::hasTable('rider_account_ledger')) {
            throw new \RuntimeException('Rider account ledger is not available.');
        }

        return DB::transaction(function () use ($riderUserId, $signedAmount, $entryType, $referenceType, $referenceId, $description, $createdBy) {
            $current = (int) (RiderAccountLedgerEntry::query()
                ->where('rider_user_id', $riderUserId)
                ->lockForUpdate()
                ->latest('id')
                ->value('balance_after') ?? 0);

            $next = $current + $signedAmount;

            return RiderAccountLedgerEntry::query()->create([
                'rider_user_id' => $riderUserId,
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
