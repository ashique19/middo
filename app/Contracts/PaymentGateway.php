<?php

namespace App\Contracts;

/**
 * Bound in AppServiceProvider. Default driver is EPS (eps.com.bd);
 * use PAYMENT_GATEWAY_DRIVER=pseudo for local/test confirm-button checkout.
 */
interface PaymentGateway
{
    public function driver(): string;

    /**
     * @param  array<string, mixed>  $metadata  Cart fingerprint / order context
     * @return array{token: string, amount: int, paid: bool, payment_url: string}
     */
    public function createCheckout(int $userId, int $amount, array $metadata = []): array;

    public function markPaid(string $token): bool;

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{ok: bool, message?: string, amount?: int}
     */
    public function consumePaid(string $token, int $userId, int $amount, array $metadata = []): array;

    public function find(string $token): ?array;

    public function paymentUrl(string $token): string;
}
