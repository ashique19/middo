<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MimSms
{
    public static function send(string $mobile, string $message): bool
    {
        $formatted = OrderConfirmationOtp::formatMobile($mobile);

        $apiKey = config('services.mimsms.api_key');
        $userName = config('services.mimsms.user_name');
        $senderName = config('services.mimsms.sender_name');
        $baseUrl = config('services.mimsms.base_url');

        if (! filled($apiKey) || ! filled($userName) || ! filled($senderName) || ! filled($baseUrl)) {
            Log::warning('MimSMS skipped — credentials incomplete.', [
                'has_api_key' => filled($apiKey),
                'has_user_name' => filled($userName),
                'has_sender' => filled($senderName),
            ]);

            return false;
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(20)
                ->post($baseUrl, [
                    'apiKey' => $apiKey,
                    'userName' => $userName,
                    'campaignName' => 'null',
                    'senderName' => $senderName,
                    'transactionType' => 'T',
                    'mobileNumber' => $formatted,
                    'message' => $message,
                ]);

            $body = $response->json();
            $accepted = $response->successful() && self::responseLooksSuccessful($body);

            if (! $accepted) {
                Log::warning('MimSMS send rejected', [
                    'mobile' => $formatted,
                    'http_status' => $response->status(),
                    'body' => $body ?? $response->body(),
                ]);
            }

            return $accepted;
        } catch (\Throwable $e) {
            Log::warning('MimSMS send failed', [
                'mobile' => $formatted,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  mixed  $body
     */
    protected static function responseLooksSuccessful(mixed $body): bool
    {
        if (! is_array($body) || $body === []) {
            // Some MimSMS responses are empty on success with HTTP 200.
            return true;
        }

        foreach (['statusCode', 'status', 'Status', 'status_code'] as $key) {
            if (! array_key_exists($key, $body)) {
                continue;
            }

            $value = strtolower((string) $body[$key]);

            if (in_array($value, ['200', 'ok', 'success', 'successful'], true)) {
                return true;
            }

            if (in_array($value, ['0', 'fail', 'failed', 'error'], true)) {
                return false;
            }
        }

        if (isset($body['error']) || isset($body['errors'])) {
            return false;
        }

        return true;
    }
}
