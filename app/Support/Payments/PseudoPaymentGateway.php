<?php

namespace App\Support\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Temporary gateway that mimics a hosted checkout.
 * Replace by binding a real driver to PaymentGateway — call sites stay the same.
 */
class PseudoPaymentGateway implements PaymentGateway
{
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

        Cache::put($this->cacheKey($token), $payload, now()->addMinutes(30));

        return [
            'token' => $token,
            'amount' => $amount,
            'paid' => false,
            'payment_url' => $this->paymentUrl($token),
        ];
    }

    public function markPaid(string $token): bool
    {
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            return false;
        }

        $payload['paid'] = true;
        $payload['paid_at'] = now()->toIso8601String();
        Cache::put($key, $payload, now()->addMinutes(30));

        return true;
    }

    public function consumePaid(string $token, int $userId, int $amount, array $metadata = []): array
    {
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Payment session expired or invalid. Start payment again.'];
        }

        if ((int) ($payload['user_id'] ?? 0) !== $userId) {
            return ['ok' => false, 'message' => 'Payment session does not belong to this account.'];
        }

        if (! ($payload['paid'] ?? false)) {
            return ['ok' => false, 'message' => 'Payment is not completed yet.'];
        }

        if ((int) ($payload['amount'] ?? 0) !== $amount) {
            return ['ok' => false, 'message' => 'Paid amount does not match the required charge.'];
        }

        if (($payload['fingerprint'] ?? '') !== $this->fingerprint($metadata)) {
            return ['ok' => false, 'message' => 'Cart changed after payment. Pay again for the updated order.'];
        }

        Cache::forget($key);

        return ['ok' => true, 'amount' => $amount];
    }

    public function find(string $token): ?array
    {
        $payload = Cache::get($this->cacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    public function paymentUrl(string $token): string
    {
        return URL::temporarySignedRoute(
            'corporate.gateway-prepay.show',
            now()->addMinutes(30),
            ['token' => $token]
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function fingerprint(array $metadata): string
    {
        ksort($metadata);

        return hash('sha256', json_encode($metadata));
    }

    protected function cacheKey(string $token): string
    {
        return 'payment_gateway_checkout_'.$token;
    }
}
