<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmClient
{
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
    ): array {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        }

        $serverKey = config('services.fcm.server_key');

        if (! filled($serverKey)) {
            Log::info('FCM skipped — services.fcm.server_key is not configured.', [
                'token_count' => count($tokens),
                'title' => $title,
            ]);

            return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        }

        $sent = 0;
        $failed = 0;

        // FCM legacy HTTP API — one request per token keeps invalid-token cleanup simple.
        foreach ($tokens as $token) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key='.$serverKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => array_merge($data, [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]),
                ]);

                if ($response->successful() && ($response->json('success') ?? 0) >= 1) {
                    $sent++;
                } else {
                    $failed++;
                    Log::warning('FCM send failed', [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('FCM send exception', ['error' => $e->getMessage()]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => false];
    }
}
