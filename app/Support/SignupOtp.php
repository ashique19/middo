<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class SignupOtp
{
    public static function cacheKey(string $mobile): string
    {
        return 'signup_otp_'.OrderConfirmationOtp::formatMobile($mobile);
    }

    public static function generate(string $mobile): string
    {
        $otp = config('app.debug') ? '1234' : (string) random_int(1000, 9999);
        Cache::put(self::cacheKey($mobile), $otp, 300);

        return $otp;
    }

    /**
     * @return array{ok: bool, message: string, debug_otp?: string}
     */
    public static function send(string $mobile): array
    {
        $otp = self::generate($mobile);
        $message = "Your Middo signup verification code is: {$otp}. Valid for 5 minutes.";

        $sent = MimSms::send($mobile, $message);

        if (! $sent && (config('app.debug') || app()->environment('testing'))) {
            $sent = true;
        }

        if (! $sent) {
            return [
                'ok' => false,
                'message' => 'SMS channel transmission error. Please retry.',
            ];
        }

        $payload = [
            'ok' => true,
            'message' => 'Verification code sent successfully.',
        ];

        if (config('app.debug') || app()->environment('testing')) {
            $payload['debug_otp'] = $otp;
        }

        return $payload;
    }

    public static function verify(string $mobile, string $otp): bool
    {
        $cached = Cache::get(self::cacheKey($mobile));

        if (! $cached || (string) $otp !== (string) $cached) {
            return false;
        }

        Cache::forget(self::cacheKey($mobile));

        return true;
    }
}
