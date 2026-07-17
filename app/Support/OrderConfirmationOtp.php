<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class OrderConfirmationOtp
{
    public static function cacheKey(string $mobile): string
    {
        return 'order_confirmation_otp_'.self::formatMobile($mobile);
    }

    public static function formatMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '880')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '88'.$digits;
        }

        return '880'.$digits;
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
        $message = "Your Middo Order Confirmation Code is: {$otp}. Enter this code to finalize your schedule.";

        $sent = MimSms::send($mobile, $message);

        // Local/debug and automated tests should not block checkout when SMS is unavailable.
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
            'message' => 'Confirmation code sent successfully.',
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
