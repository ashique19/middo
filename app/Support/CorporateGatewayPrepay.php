<?php

namespace App\Support;

use App\Contracts\PaymentGateway;

/**
 * @deprecated Prefer injecting App\Contracts\PaymentGateway.
 * Kept as a thin facade so older call sites keep working until swapped.
 */
class CorporateGatewayPrepay
{
    public static function cacheKey(string $token): string
    {
        return 'payment_gateway_checkout_'.$token;
    }

    /**
     * @param  array<string, mixed>  $cartFingerprint
     * @return array{token: string, amount: int, paid: bool, payment_url?: string}
     */
    public static function create(int $userId, int $amount, array $cartFingerprint): array
    {
        return app(PaymentGateway::class)->createCheckout($userId, $amount, $cartFingerprint);
    }

    /**
     * @param  array<string, mixed>  $cartFingerprint
     */
    public static function fingerprint(array $cartFingerprint): string
    {
        $gateway = app(PaymentGateway::class);

        return method_exists($gateway, 'fingerprint')
            ? $gateway->fingerprint($cartFingerprint)
            : hash('sha256', (string) json_encode($cartFingerprint));
    }

    public static function markPaid(string $token): bool
    {
        return app(PaymentGateway::class)->markPaid($token);
    }

    /**
     * @param  array<string, mixed>  $cartFingerprint
     * @return array{ok: bool, message?: string, amount?: int}
     */
    public static function consumePaidToken(string $token, int $userId, int $amount, array $cartFingerprint): array
    {
        return app(PaymentGateway::class)->consumePaid($token, $userId, $amount, $cartFingerprint);
    }
}
