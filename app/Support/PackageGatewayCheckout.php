<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Stores package checkout draft alongside a PaymentGateway session so OTP
 * can happen on a separate screen after online payment succeeds.
 */
class PackageGatewayCheckout
{
    public const PURPOSE = 'package_subscribe';

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
     */
    public static function storeDraft(string $token, array $draft): void
    {
        Cache::put(self::draftKey($token), $draft, now()->addMinutes(45));
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

    protected static function draftKey(string $token): string
    {
        return 'package_gateway_draft_'.$token;
    }
}
