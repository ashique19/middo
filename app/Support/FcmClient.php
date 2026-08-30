<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmClient
{
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data
     * @return array{sent: int, failed: int, skipped: bool}
     */
    public static function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        string $androidChannelId = 'middo_orders',
    ): array {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        }

        $projectId = self::projectId();
        $accessToken = self::accessToken();

        if ($projectId === null || $accessToken === null) {
            Log::info('FCM skipped — Firebase service account credentials are not configured.', [
                'token_count' => count($tokens),
                'title' => $title,
                'credentials_path' => config('services.fcm.credentials'),
            ]);

            return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        }

        $sent = 0;
        $failed = 0;
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // Stringify data values — FCM v1 requires map<string, string>.
        $dataPayload = [];
        foreach (array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']) as $key => $value) {
            $dataPayload[(string) $key] = (string) $value;
        }

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => $dataPayload,
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'sound' => 'default',
                                    'channel_id' => $androidChannelId,
                                ],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $sent++;
                } else {
                    $failed++;
                    Log::warning('FCM v1 send failed', [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('FCM v1 send exception', ['error' => $e->getMessage()]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => false];
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    public static function credentials(): ?array
    {
        $path = self::resolveCredentialsPath(config('services.fcm.credentials'));

        if ($path === null || ! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        $projectId = (string) (config('services.fcm.project_id') ?: ($decoded['project_id'] ?? ''));
        $clientEmail = (string) ($decoded['client_email'] ?? '');
        $privateKey = (string) ($decoded['private_key'] ?? '');

        if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
            return null;
        }

        return [
            'project_id' => $projectId,
            'client_email' => $clientEmail,
            'private_key' => $privateKey,
        ];
    }

    /**
     * Resolve a relative credentials path against the Laravel base path.
     */
    public static function resolveCredentialsPath(mixed $path): ?string
    {
        if (! filled($path) || ! is_string($path)) {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        $fromBase = base_path($path);
        if (is_file($fromBase)) {
            return $fromBase;
        }

        return null;
    }

    public static function projectId(): ?string
    {
        return self::credentials()['project_id'] ?? null;
    }

    public static function accessToken(): ?string
    {
        $credentials = self::credentials();

        if ($credentials === null) {
            return null;
        }

        $cacheKey = 'fcm.oauth_access_token.'.md5($credentials['client_email']);

        try {
            return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials) {
                $jwt = self::buildServiceAccountJwt(
                    $credentials['client_email'],
                    $credentials['private_key'],
                );

                $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if (! $response->successful() || ! filled($response->json('access_token'))) {
                    Log::warning('FCM OAuth token exchange failed', [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]);

                    throw new \RuntimeException('Unable to obtain FCM OAuth access token.');
                }

                return (string) $response->json('access_token');
            });
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            Log::warning('FCM access token unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected static function buildServiceAccountJwt(string $clientEmail, string $privateKey): string
    {
        $now = time();
        $header = self::base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = self::base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => self::MESSAGING_SCOPE,
            'aud' => self::OAUTH_TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$payload;

        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new \RuntimeException('Invalid Firebase service account private key.');
        }

        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256);

        if (! $ok) {
            throw new \RuntimeException('Failed to sign FCM service account JWT.');
        }

        return $unsigned.'.'.self::base64UrlEncode($signature);
    }

    protected static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
