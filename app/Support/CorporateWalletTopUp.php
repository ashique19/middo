<?php

namespace App\Support;

use App\Contracts\PaymentGateway;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Wallet top-ups reuse the same PseudoPaymentGateway checkout as order prepay.
 * Payment is credited when the hosted checkout marks the session paid.
 */
class CorporateWalletTopUp
{
    public const PURPOSE = 'wallet_top_up';

    /**
     * @return array{token: string, amount: int, paid: bool, payment_url: string}
     */
    public static function createCheckout(User $user, int $amount): array
    {
        return app(PaymentGateway::class)->createCheckout($user->id, $amount, [
            'purpose' => self::PURPOSE,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Credit Middo Balance once for a paid wallet session. Idempotent.
     *
     * @return array{ok: bool, message?: string, amount?: int, balance?: int}
     */
    public static function creditIfPaid(string $token): array
    {
        $gateway = app(PaymentGateway::class);
        $payload = $gateway->find($token);

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Payment session expired or invalid.'];
        }

        if (($payload['metadata']['purpose'] ?? null) !== self::PURPOSE) {
            return ['ok' => false, 'message' => 'Not a wallet top-up payment.'];
        }

        if (! ($payload['paid'] ?? false)) {
            return ['ok' => false, 'message' => 'Payment is not completed yet.'];
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        $amount = (int) ($payload['amount'] ?? 0);
        $user = User::query()->find($userId);

        if ($payload['credited'] ?? false) {
            return [
                'ok' => true,
                'amount' => $amount,
                'balance' => $user ? (int) $user->balance : null,
                'message' => 'Balance already topped up.',
            ];
        }

        if ($userId < 1 || $amount < 1 || ! $user) {
            return ['ok' => false, 'message' => 'Invalid wallet payment payload.'];
        }

        // Idempotency: existing ledger row for this gateway token.
        $existing = WalletTransaction::query()
            ->where('gateway_token', $token)
            ->where('type', WalletTransaction::TYPE_TOPUP)
            ->first();

        if ($existing) {
            $payload['credited'] = true;
            Cache::put(CorporateGatewayPrepay::cacheKey($token), $payload, now()->addMinutes(30));

            return [
                'ok' => true,
                'amount' => $amount,
                'balance' => (int) $user->fresh()->balance,
                'message' => 'Balance already topped up.',
            ];
        }

        return DB::transaction(function () use ($token, $payload, $user, $amount) {
            WalletLedger::credit(
                $user,
                $amount,
                WalletTransaction::TYPE_TOPUP,
                'Wallet top-up via payment gateway',
                null,
                $token
            );

            $payload['credited'] = true;
            $payload['credited_at'] = now()->toIso8601String();
            Cache::put(CorporateGatewayPrepay::cacheKey($token), $payload, now()->addMinutes(30));

            return [
                'ok' => true,
                'amount' => $amount,
                'balance' => (int) $user->fresh()->balance,
                'message' => 'Middo Balance topped up successfully.',
            ];
        });
    }
}
