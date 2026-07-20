<?php

namespace App\Support\Payments;

use App\Contracts\PaymentGateway;
use App\Support\Payments\Concerns\StoresCheckoutSessions;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Temporary gateway that mimics a hosted checkout.
 * Prefer EpsPaymentGateway in real environments (PAYMENT_GATEWAY_DRIVER=eps).
 */
class PseudoPaymentGateway implements PaymentGateway
{
    use StoresCheckoutSessions;

    public function driver(): string
    {
        return 'pseudo';
    }

    public function createCheckout(int $userId, int $amount, array $metadata = []): array
    {
        $token = Str::random(40);
        $payload = [
            'driver' => $this->driver(),
            'user_id' => $userId,
            'amount' => $amount,
            'fingerprint' => $this->fingerprint($metadata),
            'metadata' => $metadata,
            'paid' => false,
            'created_at' => now()->toIso8601String(),
        ];

        $this->storeSession($token, $payload);

        return [
            'token' => $token,
            'amount' => $amount,
            'paid' => false,
            'payment_url' => $this->paymentUrl($token),
        ];
    }

    public function paymentUrl(string $token): string
    {
        return URL::temporarySignedRoute(
            'corporate.gateway-prepay.show',
            now()->addMinutes(45),
            ['token' => $token]
        );
    }
}
