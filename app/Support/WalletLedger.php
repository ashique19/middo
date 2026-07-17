<?php

namespace App\Support;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletLedger
{
    public static function credit(
        User $user,
        int $amount,
        string $type,
        ?string $description = null,
        ?Model $reference = null,
        ?string $gatewayToken = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $reference, $gatewayToken) {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->balance = (int) $locked->balance + $amount;
            $locked->save();

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => (int) $locked->balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'gateway_token' => $gatewayToken,
            ]);
        });
    }

    public static function debit(
        User $user,
        int $amount,
        ?string $description = null,
        ?Model $reference = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $description, $reference) {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ((int) $locked->balance < $amount) {
                throw new \RuntimeException('Insufficient Middo Balance.');
            }

            $locked->balance = (int) $locked->balance - $amount;
            $locked->save();

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => WalletTransaction::TYPE_DEBIT,
                'amount' => $amount,
                'balance_after' => (int) $locked->balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
            ]);
        });
    }
}
