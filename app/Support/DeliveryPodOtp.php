<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * One-time delivery confirmation codes for proof-of-delivery.
 * Cached per order; SMS uses MimSms when available.
 */
class DeliveryPodOtp
{
    public static function cacheKey(int $orderId): string
    {
        return 'delivery_pod_otp_'.$orderId;
    }

    public static function generate(int $orderId): string
    {
        $otp = (config('app.debug') || app()->environment(['local', 'testing']))
            ? '1234'
            : (string) random_int(1000, 9999);

        Cache::put(self::cacheKey($orderId), $otp, now()->addMinutes(15));

        return $otp;
    }

    /**
     * @return array{ok: bool, message: string, debug_otp?: string, mobile?: string}
     */
    public static function sendForOrder(Order $order): array
    {
        $order->loadMissing('user');
        $party = method_exists($order, 'partyPayload')
            ? $order->partyPayload()
            : [];
        $mobile = (string) (
            $party['receiver_mobile']
            ?? $order->receiver_mobile
            ?? $order->user?->mobile
            ?? ''
        );

        if ($mobile === '') {
            return [
                'ok' => false,
                'message' => 'No receiver mobile on this order to send a delivery code.',
            ];
        }

        $otp = self::generate((int) $order->id);
        $message = "Middo delivery code for order #{$order->id}: {$otp}. Share with your rider to confirm delivery.";

        $sent = class_exists(MimSms::class)
            ? MimSms::send($mobile, $message)
            : false;

        if (! $sent && (config('app.debug') || app()->environment(['local', 'testing']))) {
            $sent = true;
        }

        if (! $sent) {
            return [
                'ok' => false,
                'message' => 'Could not send delivery code SMS. Try again.',
                'mobile' => $mobile,
            ];
        }

        $payload = [
            'ok' => true,
            'message' => 'Delivery confirmation code sent to the receiver.',
            'mobile' => $mobile,
        ];

        if (config('app.debug') || app()->environment(['local', 'testing'])) {
            $payload['debug_otp'] = $otp;
        }

        return $payload;
    }

    public static function verify(int $orderId, string $otp): bool
    {
        $cached = Cache::get(self::cacheKey($orderId));
        if (! $cached || (string) $otp !== (string) $cached) {
            return false;
        }

        Cache::forget(self::cacheKey($orderId));

        return true;
    }

    public static function isRequired(): bool
    {
        if (class_exists(MiddoSettings::class) && method_exists(MiddoSettings::class, 'deliveryRequirePod')) {
            return MiddoSettings::deliveryRequirePod();
        }

        return true;
    }
};
