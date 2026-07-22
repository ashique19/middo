<?php

namespace App\Support;

use App\Contracts\PaymentGateway;
use App\Models\MealPackage;
use App\Models\PackageCheckoutIntent;
use App\Models\PackageSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Package online checkout: pay first, then OTP.
 * Persists intents in DB so paid-but-abandoned OTP flows remain traceable.
 */
class PackageGatewayCheckout
{
    public const PURPOSE = 'package_subscribe';

    public const PAID_SESSION_TTL_DAYS = 14;

    /**
     * Cart fingerprint metadata stored on the payment session.
     *
     * @param  array<int, array{menu_item_id: int, day_count: int}>  $selections
     * @param  array<int, int>  $omittedWeekdays
     * @return array<string, mixed>
     */
    public static function cartMetadata(
        int $mealPackageId,
        int $quantity,
        array $omittedWeekdays,
        string $targetMonth,
        array $selections,
        int $amount,
        ?int $areaId = null
    ): array {
        return [
            'purpose' => self::PURPOSE,
            'meal_package_id' => $mealPackageId,
            'quantity' => $quantity,
            'omitted_weekdays' => PackageBilling::normalizeOmittedWeekdays($omittedWeekdays),
            'target_month' => PackageBilling::normalizeTargetMonth($targetMonth),
            'selections' => collect($selections)
                ->map(fn ($row) => [
                    'menu_item_id' => (int) $row['menu_item_id'],
                    'day_count' => (int) $row['day_count'],
                ])
                ->sortBy('menu_item_id')
                ->values()
                ->all(),
            'amount' => $amount,
            'area_id' => $areaId,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $metadata
     */
    public static function rememberIntent(
        int $userId,
        string $token,
        array $metadata,
        array $draft
    ): PackageCheckoutIntent {
        self::storeDraft($token, $draft);

        return PackageCheckoutIntent::query()->updateOrCreate(
            ['payment_token' => $token],
            [
                'user_id' => $userId,
                'status' => PackageCheckoutIntent::STATUS_AWAITING_PAYMENT,
                'meal_package_id' => (int) ($metadata['meal_package_id'] ?? 0),
                'quantity' => (int) ($metadata['quantity'] ?? 1),
                'omitted_weekdays' => $metadata['omitted_weekdays'] ?? [5, 6],
                'target_month' => (string) ($metadata['target_month'] ?? ''),
                'selections' => $metadata['selections'] ?? [],
                'amount' => (int) ($metadata['amount'] ?? 0),
                'customer_name' => (string) ($draft['customer_name'] ?? ''),
                'mobile' => (string) ($draft['mobile'] ?? ''),
                'address_line1' => (string) ($draft['address_line1'] ?? ''),
                'city_id' => (int) ($draft['city_id'] ?? 0),
                'area_id' => (int) ($draft['area_id'] ?? 0),
                'delivery_window' => (string) ($draft['delivery_window'] ?? '12:00 PM'),
                'paid_at' => null,
                'package_subscription_id' => null,
            ]
        );
    }

    public static function markIntentPaid(string $token): ?PackageCheckoutIntent
    {
        return DB::transaction(function () use ($token) {
            /** @var PackageCheckoutIntent|null $intent */
            $intent = PackageCheckoutIntent::query()
                ->where('payment_token', $token)
                ->lockForUpdate()
                ->first();

            if (! $intent) {
                return null;
            }

            if ($intent->status === PackageCheckoutIntent::STATUS_COMPLETED) {
                return $intent;
            }

            if ($intent->status !== PackageCheckoutIntent::STATUS_PAID_AWAITING_OTP) {
                $intent->update([
                    'status' => PackageCheckoutIntent::STATUS_PAID_AWAITING_OTP,
                    'paid_at' => $intent->paid_at ?? now(),
                ]);
            }

            self::extendPaidGatewaySession($token, $intent);

            return $intent->fresh();
        });
    }

    public static function findIntent(string $token): ?PackageCheckoutIntent
    {
        return PackageCheckoutIntent::query()->where('payment_token', $token)->first();
    }

    public static function latestPaidAwaitingOtp(int $userId): ?PackageCheckoutIntent
    {
        return PackageCheckoutIntent::query()
            ->forUser($userId)
            ->paidAwaitingOtp()
            ->with('package:id,name')
            ->latest('paid_at')
            ->latest('id')
            ->first();
    }

    /**
     * Send OTP if enough time has passed since the last send (default 5 minutes).
     *
     * @return array{ok:bool,message?:string,debug_otp?:string,sent:bool}
     */
    public static function pokeOtp(PackageCheckoutIntent $intent, int $cooldownSeconds = 300): array
    {
        if (! $intent->isPaidAwaitingOtp()) {
            return ['ok' => false, 'message' => 'This checkout is not awaiting OTP.', 'sent' => false];
        }

        $last = $intent->otp_last_sent_at;
        if ($last && $last->gt(now()->subSeconds($cooldownSeconds))) {
            return [
                'ok' => true,
                'sent' => false,
                'message' => 'OTP already sent recently. Check your SMS or wait a moment to resend.',
            ];
        }

        $result = OrderConfirmationOtp::send($intent->mobile);
        if ($result['ok'] ?? false) {
            $intent->update(['otp_last_sent_at' => now()]);
        }

        return array_merge($result, ['sent' => (bool) ($result['ok'] ?? false)]);
    }

    public static function markCompleted(string $token, PackageSubscription $subscription): void
    {
        PackageCheckoutIntent::query()
            ->where('payment_token', $token)
            ->update([
                'status' => PackageCheckoutIntent::STATUS_COMPLETED,
                'package_subscription_id' => $subscription->id,
            ]);

        self::forgetDraft($token);
    }

    /**
     * Resolve draft fields for confirm/create — DB intent first, cache fallback.
     *
     * @return array{intent:?PackageCheckoutIntent,draft:array<string,mixed>,metadata:array<string,mixed>,paid:bool,amount:int}|null
     */
    public static function resolve(string $token, int $userId): ?array
    {
        $intent = self::findIntent($token);
        $gatewayPayload = app(PaymentGateway::class)->find($token);
        $cacheDraft = self::findDraft($token);

        if ($intent) {
            if ((int) $intent->user_id !== $userId) {
                return null;
            }

            // Sync paid flag from live gateway session if needed.
            if ($intent->isAwaitingPayment() && is_array($gatewayPayload) && ($gatewayPayload['paid'] ?? false)) {
                $intent = self::markIntentPaid($token) ?? $intent->fresh();
            }

            if ($intent->isPaidAwaitingOtp()) {
                self::extendPaidGatewaySession($token, $intent);
            }

            return [
                'intent' => $intent,
                'draft' => [
                    'customer_name' => $intent->customer_name,
                    'mobile' => $intent->mobile,
                    'address_line1' => $intent->address_line1,
                    'city_id' => (int) $intent->city_id,
                    'area_id' => (int) $intent->area_id,
                    'delivery_window' => $intent->delivery_window,
                ],
                'metadata' => self::cartMetadata(
                    (int) $intent->meal_package_id,
                    (int) $intent->quantity,
                    $intent->omitted_weekdays ?? [5, 6],
                    (string) $intent->target_month,
                    $intent->selections ?? [],
                    (int) $intent->amount,
                    (int) $intent->area_id ?: null
                ),
                'paid' => $intent->isPaidAwaitingOtp() || $intent->status === PackageCheckoutIntent::STATUS_COMPLETED
                    || (bool) ($gatewayPayload['paid'] ?? false),
                'amount' => (int) $intent->amount,
            ];
        }

        // Legacy cache-only path (pre-intent).
        if (! is_array($gatewayPayload) || ! is_array($cacheDraft)) {
            return null;
        }

        if ((int) ($gatewayPayload['user_id'] ?? 0) !== $userId) {
            return null;
        }

        $metadata = is_array($gatewayPayload['metadata'] ?? null) ? $gatewayPayload['metadata'] : [];
        if (($metadata['purpose'] ?? null) !== self::PURPOSE) {
            return null;
        }

        return [
            'intent' => null,
            'draft' => $cacheDraft,
            'metadata' => $metadata,
            'paid' => (bool) ($gatewayPayload['paid'] ?? false),
            'amount' => (int) ($gatewayPayload['amount'] ?? 0),
        ];
    }

    /**
     * Consume gateway payment, falling back to a locked paid intent when the cache session expired.
     *
     * @param  array<string, mixed>  $fingerprint
     * @return array{ok:bool,message?:string}
     */
    public static function consumePayment(string $token, int $userId, int $amount, array $fingerprint): array
    {
        self::ensureGatewaySession($token);

        $consumed = app(PaymentGateway::class)->consumePaid($token, $userId, $amount, $fingerprint);
        if ($consumed['ok'] ?? false) {
            return $consumed;
        }

        $intent = self::findIntent($token);
        if (
            $intent
            && (int) $intent->user_id === $userId
            && $intent->isPaidAwaitingOtp()
            && (int) $intent->amount === $amount
        ) {
            $expected = self::cartMetadata(
                (int) $intent->meal_package_id,
                (int) $intent->quantity,
                $intent->omitted_weekdays ?? [5, 6],
                (string) $intent->target_month,
                $intent->selections ?? [],
                (int) $intent->amount,
                (int) $intent->area_id ?: null
            );

            if (self::fingerprint($expected) === self::fingerprint($fingerprint)) {
                return ['ok' => true, 'amount' => $amount, 'via' => 'intent'];
            }
        }

        return $consumed;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function fingerprint(array $metadata): string
    {
        ksort($metadata);

        return hash('sha256', (string) json_encode($metadata));
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public static function storeDraft(string $token, array $draft): void
    {
        Cache::put(self::draftKey($token), $draft, now()->addDays(self::PAID_SESSION_TTL_DAYS));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findDraft(string $token): ?array
    {
        $draft = Cache::get(self::draftKey($token));

        return is_array($draft) ? $draft : null;
    }

    public static function forgetDraft(string $token): void
    {
        Cache::forget(self::draftKey($token));
    }

    public static function confirmUrl(string $token): string
    {
        return route('corporates.packages.confirm', ['token' => $token]);
    }

    protected static function extendPaidGatewaySession(string $token, PackageCheckoutIntent $intent): void
    {
        $key = 'payment_gateway_checkout_'.$token;
        $existing = Cache::get($key);

        if (is_array($existing)) {
            $existing['paid'] = true;
            $existing['paid_at'] = $existing['paid_at'] ?? now()->toIso8601String();
            Cache::put($key, $existing, now()->addDays(self::PAID_SESSION_TTL_DAYS));

            return;
        }

        // Rebuild a paid session so consumePaid still works after short cache TTL.
        $metadata = self::cartMetadata(
            (int) $intent->meal_package_id,
            (int) $intent->quantity,
            $intent->omitted_weekdays ?? [5, 6],
            (string) $intent->target_month,
            $intent->selections ?? [],
            (int) $intent->amount,
            (int) $intent->area_id ?: null
        );

        Cache::put($key, [
            'token' => $token,
            'user_id' => (int) $intent->user_id,
            'amount' => (int) $intent->amount,
            'paid' => true,
            'paid_at' => optional($intent->paid_at)->toIso8601String() ?? now()->toIso8601String(),
            'metadata' => $metadata,
            'fingerprint' => self::fingerprint($metadata),
            'restored_from_intent' => true,
        ], now()->addDays(self::PAID_SESSION_TTL_DAYS));

        self::storeDraft($token, [
            'customer_name' => $intent->customer_name,
            'mobile' => $intent->mobile,
            'address_line1' => $intent->address_line1,
            'city_id' => (int) $intent->city_id,
            'area_id' => (int) $intent->area_id,
            'delivery_window' => $intent->delivery_window,
        ]);
    }

    protected static function ensureGatewaySession(string $token): void
    {
        $intent = self::findIntent($token);
        if ($intent && $intent->isPaidAwaitingOtp()) {
            self::extendPaidGatewaySession($token, $intent);
        }
    }

    protected static function draftKey(string $token): string
    {
        return 'package_gateway_draft_'.$token;
    }
}
