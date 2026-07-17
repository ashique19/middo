<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CorporateGatewayPrepay
{
    public static function cacheKey(string $token): string
    {
        return 'corporate_gateway_prepay_'.$token;
    }

    /**
     * @param  array<string, mixed>  $cartFingerprint
     * @return array{token: string, amount: int, paid: bool}
     */
    public static function create(int $userId, int $amount, array $cartFingerprint): array
    {
        $token = Str::random(40);
        $payload = [
            'user_id' => $userId,
            'amount' => $amount,
            'fingerprint' => self::fingerprint($cartFingerprint),
            'paid' => false,
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put(self::cacheKey($token), $payload, now()->addMinutes(30));

        return [
            'token' => $token,
            'amount' => $amount,
            'paid' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $cartFingerprint
     */
    public static function fingerprint(array $cartFingerprint): string
    {
        ksort($cartFingerprint);

        return hash('sha256', json_encode($cartFingerprint));
    }

    public static function markPaid(string $token): bool
    {
        $key = self::cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            return false;
        }

        $payload['paid'] = true;
        $payload['paid_at'] = now()->toIso8601String();
        Cache::put($key, $payload, now()->addMinutes(30));

        return true;
    }

    /**
     * @param  array<string, mixed>  $cartFingerprint
     * @return array{ok: bool, message?: string, amount?: int}
     */
    public static function consumePaidToken(string $token, int $userId, int $amount, array $cartFingerprint): array
    {
        $key = self::cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Payment session expired or invalid. Start gateway payment again.'];
        }

        if ((int) ($payload['user_id'] ?? 0) !== $userId) {
            return ['ok' => false, 'message' => 'Payment session does not belong to this account.'];
        }

        if (! ($payload['paid'] ?? false)) {
            return ['ok' => false, 'message' => 'Gateway payment is not completed yet.'];
        }

        if ((int) ($payload['amount'] ?? 0) !== $amount) {
            return ['ok' => false, 'message' => 'Paid amount does not match the required prepayment.'];
        }

        if (($payload['fingerprint'] ?? '') !== self::fingerprint($cartFingerprint)) {
            return ['ok' => false, 'message' => 'Cart changed after payment. Pay again for the updated order.'];
        }

        Cache::forget($key);

        return ['ok' => true, 'amount' => $amount];
    }
}
