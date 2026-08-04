<?php

namespace App\Support;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;

/**
 * Resolve effective rider commission for a run (hybrid sources).
 *
 * - Lunch kitchen→corporate: rider override ?? (menu.delivery_commission × qty)
 * - Box / custom: rider override ?? settings default
 * - D3: null override silently uses default; effective 0 → no pay / hide UI
 */
class RiderCommission
{
    /**
     * Effective commission amount for a settings-backed run type (per box / per run).
     */
    public static function forSettingsRun(User $rider, string $runType): int
    {
        if (! DeliveryRunType::isSettingsBacked($runType)) {
            throw new \InvalidArgumentException("Run type [{$runType}] is not settings-backed.");
        }

        $override = self::riderOverride($rider, $runType);
        if ($override !== null) {
            return max(0, $override);
        }

        return MiddoSettings::deliveryCommissionDefault($runType);
    }

    /**
     * Effective lunch commission for an order at run start (per order).
     */
    public static function forLunchOrder(User $rider, Order $order): int
    {
        $override = self::riderOverride($rider, DeliveryRunType::KITCHEN_TO_CORPORATE);
        if ($override !== null) {
            return max(0, $override);
        }

        $order->loadMissing('menuItem');
        $menu = $order->menuItem instanceof MenuItem ? $order->menuItem : null;
        $unit = (int) ($menu?->delivery_commission ?? 0);
        $qty = max(1, (int) $order->quantity);

        return max(0, $unit * $qty);
    }

    public static function shouldShow(int $effectiveAmount): bool
    {
        return $effectiveAmount > 0;
    }

    /**
     * @return array<string, int|null>
     */
    public static function overridesMap(User $rider): array
    {
        $raw = $rider->rider_commission_overrides;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (DeliveryRunType::all() as $type) {
            if (! array_key_exists($type, $raw) || $raw[$type] === null || $raw[$type] === '') {
                continue;
            }
            $out[$type] = max(0, (int) $raw[$type]);
        }

        return $out;
    }

    public static function riderOverride(User $rider, string $runType): ?int
    {
        $map = self::overridesMap($rider);

        return array_key_exists($runType, $map) ? $map[$runType] : null;
    }

    /**
     * Normalize form input into a storable overrides array (omit empties).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, int>|null
     */
    public static function normalizeOverridesInput(array $input): ?array
    {
        $clean = [];
        foreach (DeliveryRunType::all() as $type) {
            if (! array_key_exists($type, $input)) {
                continue;
            }
            $value = $input[$type];
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$type] = max(0, (int) $value);
        }

        return $clean === [] ? null : $clean;
    }
}
