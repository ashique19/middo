<?php

namespace App\Support\Payments\Eps;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin HTTP client for EPS (Easy Payment System) Merchant API.
 *
 * Flow: GetToken → InitializeEPS → CheckMerchantTransactionStatus
 * Hash: Base64(HMAC-SHA512(data, hashKey))
 */
class EpsClient
{
    public function __construct(
        protected array $config = []
    ) {
        $this->config = array_merge([
            'sandbox' => true,
            'merchant_id' => '',
            'store_id' => '',
            'username' => '',
            'password' => '',
            'hash_key' => '',
            'timeout' => 30,
        ], $config);
    }

    public static function fromConfig(): self
    {
        return new self(config('payments.eps', []));
    }

    public function generateHash(string $data): string
    {
        $key = (string) ($this->config['hash_key'] ?? '');

        return base64_encode(hash_hmac('sha512', $data, $key, true));
    }

    public function getToken(): string
    {
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        if ($username === '' || $password === '' || blank($this->config['hash_key'] ?? null)) {
            throw new RuntimeException('EPS credentials are not configured.');
        }

        $response = $this->http()
            ->withHeaders(['x-hash' => $this->generateHash($username)])
            ->post($this->url('/v1/Auth/GetToken'), [
                'userName' => $username,
                'password' => $password,
            ]);

        $data = $response->json() ?? [];

        if (! $response->successful() || blank($data['token'] ?? null)) {
            Log::warning('EPS GetToken failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                $data['errorMessage'] ?? $data['ErrorMessage'] ?? 'Unable to authenticate with EPS.'
            );
        }

        return (string) $data['token'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{RedirectURL: string, TransactionId: ?string, merchantTransactionId: string, raw: array}
     */
    public function initializePayment(array $payload): array
    {
        $merchantTransactionId = (string) $payload['merchantTransactionId'];
        $token = $this->getToken();

        $response = $this->http()
            ->withToken($token)
            ->withHeaders(['x-hash' => $this->generateHash($merchantTransactionId)])
            ->post($this->url('/v1/EPSEngine/InitializeEPS'), $payload);

        $data = $response->json() ?? [];
        $error = $data['ErrorMessage'] ?? $data['errorMessage'] ?? null;

        if (! $response->successful() || filled($error) || blank($data['RedirectURL'] ?? null)) {
            Log::warning('EPS InitializeEPS failed', [
                'status' => $response->status(),
                'merchant_transaction_id' => $merchantTransactionId,
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                is_string($error) && $error !== ''
                    ? $error
                    : 'Unable to initialize EPS payment.'
            );
        }

        return [
            'RedirectURL' => (string) $data['RedirectURL'],
            'TransactionId' => isset($data['TransactionId']) ? (string) $data['TransactionId'] : null,
            'merchantTransactionId' => $merchantTransactionId,
            'raw' => $data,
        ];
    }

    /**
     * @return array{success: bool, status: string, total_amount: ?float, raw: array, message?: string}
     */
    public function verifyTransaction(string $merchantTransactionId): array
    {
        $token = $this->getToken();
        $url = $this->url('/v1/EPSEngine/CheckMerchantTransactionStatus')
            .'?merchantTransactionId='.urlencode($merchantTransactionId);

        $response = $this->http()
            ->withToken($token)
            ->withHeaders(['x-hash' => $this->generateHash($merchantTransactionId)])
            ->get($url);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            Log::warning('EPS verify failed', [
                'status' => $response->status(),
                'merchant_transaction_id' => $merchantTransactionId,
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'total_amount' => null,
                'raw' => is_array($data) ? $data : [],
                'message' => 'Unable to verify EPS payment status.',
            ];
        }

        $status = strtolower((string) ($data['Status'] ?? 'unknown'));
        $total = $data['TotalAmount'] ?? null;

        return [
            'success' => $status === 'success',
            'status' => $status,
            'total_amount' => is_numeric($total) ? (float) $total : null,
            'raw' => $data,
            'message' => $data['ErrorMessage'] ?? null,
        ];
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 30));
    }

    protected function url(string $path): string
    {
        $base = ($this->config['sandbox'] ?? true)
            ? 'https://sandboxpgapi.eps.com.bd'
            : 'https://pgapi.eps.com.bd';

        return rtrim($base, '/').$path;
    }
}
