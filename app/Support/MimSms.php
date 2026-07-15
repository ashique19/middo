<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MimSms
{
    public static function send(string $mobile, string $message): bool
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '880')) {
            $formatted = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $formatted = '88'.$digits;
        } else {
            $formatted = '880'.$digits;
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post(config('services.mimsms.base_url'), [
                    'apiKey' => config('services.mimsms.api_key'),
                    'userName' => config('services.mimsms.user_name'),
                    'campaignName' => 'null',
                    'senderName' => config('services.mimsms.sender_name'),
                    'transactionType' => 'T',
                    'mobileNumber' => $formatted,
                    'message' => $message,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('MimSMS send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
