<?php

namespace App\Support;

use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MiddoBankLedger
{
    public static function balance(int $bankAccountId): int
    {
        if (! Schema::hasTable('middo_bank_ledger')) {
            return 0;
        }

        return (int) (MiddoBankLedgerEntry::query()
            ->where('middo_bank_account_id', $bankAccountId)
            ->latest('id')
            ->value('balance_after') ?? 0);
    }

    public static function defaultAccount(): ?MiddoBankAccount
    {
        if (! Schema::hasTable('middo_bank_accounts')) {
            return null;
        }

        $defaultId = MiddoSettings::defaultEpsBankAccountId();
        if ($defaultId) {
            $account = MiddoBankAccount::query()
                ->whereKey($defaultId)
                ->where('is_active', true)
                ->first();
            if ($account) {
                return $account;
            }
        }

        return MiddoBankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public static function credit(
        int $bankAccountId,
        int $amount,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
        array $extra = [],
    ): MiddoBankLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return self::write($bankAccountId, $amount, $entryType, $referenceType, $referenceId, $description, $createdBy, $extra);
    }

    public static function debit(
        int $bankAccountId,
        int $amount,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
        array $extra = [],
    ): MiddoBankLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return self::write($bankAccountId, -$amount, $entryType, $referenceType, $referenceId, $description, $createdBy, $extra);
    }

    /**
     * Post EPS settlement: credit bank with net (gross − fee). Idempotent on gateway_token.
     *
     * @param  array<string, mixed>  $epsRaw
     */
    public static function postEpsPayment(
        string $gatewayToken,
        int $grossAmount,
        array $epsRaw = [],
        ?string $merchantTransactionId = null,
        ?string $purpose = null,
        ?int $createdBy = null,
    ): ?MiddoBankLedgerEntry {
        if (! Schema::hasTable('middo_bank_ledger') || $grossAmount < 1) {
            return null;
        }

        $existing = MiddoBankLedgerEntry::query()
            ->where('gateway_token', $gatewayToken)
            ->first();
        if ($existing) {
            return $existing;
        }

        $account = self::defaultAccount();
        if (! $account) {
            return null;
        }

        $subGateway = EpsSubGateway::fromEpsRaw($epsRaw);
        $feePct = MiddoSettings::epsFeeRatePct($subGateway);
        $fee = (int) round($grossAmount * ($feePct / 100));
        $fee = max(0, min($grossAmount, $fee));
        $net = $grossAmount - $fee;

        if ($net < 1) {
            return null;
        }

        $description = sprintf(
            'EPS net ৳%s (gross ৳%s − fee ৳%s @ %s%% · %s)%s',
            number_format($net),
            number_format($grossAmount),
            number_format($fee),
            rtrim(rtrim(number_format($feePct, 2, '.', ''), '0'), '.') ?: '0',
            EpsSubGateway::label($subGateway),
            $purpose ? ' · '.$purpose : ''
        );

        try {
            return self::credit(
                (int) $account->id,
                $net,
                MiddoBankLedgerEntry::TYPE_EPS_IN_NET,
                null,
                null,
                $description,
                $createdBy,
                [
                    'sub_gateway' => $subGateway,
                    'gross_amount' => $grossAmount,
                    'fee_amount' => $fee,
                    'gateway_token' => $gatewayToken,
                    'merchant_transaction_id' => $merchantTransactionId,
                ]
            );
        } catch (\Throwable) {
            // Race on unique gateway_token
            return MiddoBankLedgerEntry::query()->where('gateway_token', $gatewayToken)->first();
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected static function write(
        int $bankAccountId,
        int $signedAmount,
        string $entryType,
        ?string $referenceType,
        ?int $referenceId,
        ?string $description,
        ?int $createdBy,
        array $extra = [],
    ): MiddoBankLedgerEntry {
        if (! Schema::hasTable('middo_bank_ledger')) {
            throw new \RuntimeException('Middo bank ledger is not available.');
        }

        return DB::transaction(function () use ($bankAccountId, $signedAmount, $entryType, $referenceType, $referenceId, $description, $createdBy, $extra) {
            MiddoBankAccount::query()->whereKey($bankAccountId)->lockForUpdate()->firstOrFail();

            $current = (int) (MiddoBankLedgerEntry::query()
                ->where('middo_bank_account_id', $bankAccountId)
                ->lockForUpdate()
                ->latest('id')
                ->value('balance_after') ?? 0);

            $next = $current + $signedAmount;
            if ($next < 0) {
                throw new \RuntimeException('Bank account balance cannot go negative.');
            }

            return MiddoBankLedgerEntry::query()->create([
                'middo_bank_account_id' => $bankAccountId,
                'amount' => $signedAmount,
                'balance_after' => $next,
                'entry_type' => $entryType,
                'sub_gateway' => $extra['sub_gateway'] ?? null,
                'gross_amount' => $extra['gross_amount'] ?? null,
                'fee_amount' => (int) ($extra['fee_amount'] ?? 0),
                'gateway_token' => $extra['gateway_token'] ?? null,
                'merchant_transaction_id' => $extra['merchant_transaction_id'] ?? null,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_by' => $createdBy,
            ]);
        });
    }
}
