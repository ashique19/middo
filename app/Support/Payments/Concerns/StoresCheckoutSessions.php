<?php

namespace App\Support\Payments\Concerns;

use Illuminate\Support\Facades\Cache;

trait StoresCheckoutSessions
{
    public function markPaid(string $token): bool
    {
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            return false;
        }

        $payload['paid'] = true;
        $payload['paid_at'] = now()->toIso8601String();
        Cache::put($key, $payload, now()->addMinutes(45));

        return true;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{ok: bool, message?: string, amount?: int}
     */
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

        if ($merchantTxn = $payload['merchant_transaction_id'] ?? null) {
            Cache::forget($this->merchantTxnCacheKey((string) $merchantTxn));
        }

        return ['ok' => true, 'amount' => $amount];
    }

    public function find(string $token): ?array
    {
        $payload = Cache::get($this->cacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    public function findTokenByMerchantTransactionId(string $merchantTransactionId): ?string
    {
        $token = Cache::get($this->merchantTxnCacheKey($merchantTransactionId));

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function fingerprint(array $metadata): string
    {
        ksort($metadata);

        return hash('sha256', (string) json_encode($metadata));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storeSession(string $token, array $payload, ?string $merchantTransactionId = null): void
    {
        Cache::put($this->cacheKey($token), $payload, now()->addMinutes(45));

        if (filled($merchantTransactionId)) {
            Cache::put(
                $this->merchantTxnCacheKey($merchantTransactionId),
                $token,
                now()->addMinutes(45)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function refreshSession(string $token, array $payload): void
    {
        Cache::put($this->cacheKey($token), $payload, now()->addMinutes(45));
    }

    protected function cacheKey(string $token): string
    {
        return 'payment_gateway_checkout_'.$token;
    }

    protected function merchantTxnCacheKey(string $merchantTransactionId): string
    {
        return 'payment_gateway_eps_txn_'.$merchantTransactionId;
    }
}
