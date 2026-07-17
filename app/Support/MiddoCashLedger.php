<?php

namespace App\Support;

use App\Models\MiddoCashLedgerEntry;
use Illuminate\Support\Facades\DB;

class MiddoCashLedger
{
    public static function balance(): int
    {
        return (int) (MiddoCashLedgerEntry::query()->latest('id')->value('balance_after') ?? 0);
    }

    public static function credit(
        int $amount,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
    ): MiddoCashLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return self::write($amount, $entryType, $referenceType, $referenceId, $description, $createdBy);
    }

    public static function debit(
        int $amount,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
    ): MiddoCashLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return self::write(-$amount, $entryType, $referenceType, $referenceId, $description, $createdBy);
    }

    protected static function write(
        int $signedAmount,
        string $entryType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $description,
        ?int $createdBy,
    ): MiddoCashLedgerEntry {
        return DB::transaction(function () use ($signedAmount, $entryType, $referenceType, $referenceId, $description, $createdBy) {
            $current = (int) (MiddoCashLedgerEntry::query()
                ->lockForUpdate()
                ->latest('id')
                ->value('balance_after') ?? 0);

            $next = $current + $signedAmount;

            if ($next < 0) {
                throw new \RuntimeException('Middo cash balance cannot go negative.');
            }

            return MiddoCashLedgerEntry::query()->create([
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
