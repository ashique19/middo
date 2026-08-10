<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MiddoSettings
{
    public const KEY_ACCEPT_WINDOW_MINUTES = 'meal.accept_window_minutes';

    public const KEY_ACCEPT_WINDOW_WARN_MINUTES = 'meal.accept_window_warn_minutes';

    /** Wall-clock time (H:i, Asia/Dhaka) when kitchen accept window opens on the delivery day. */
    public const KEY_ACCEPT_WINDOW_STARTS_AT = 'meal.accept_window_starts_at';

    /** Daily corporate order place/edit/cancel cutoff as H:i (Asia/Dhaka). */
    public const KEY_ORDER_CUTOFF_TIME = 'order.cutoff_time';

    /** Default max meals a corporate may order per delivery date. */
    public const KEY_MAX_ORDER_QTY_ALLOWED = 'order.max_order_qty_allowed';

    public const KEY_AUTO_GROUP_QUANTITY = 'meal.auto_group_quantity';

    public const KEY_MID_RUN_RESCUE = 'delivery.commission.mid_run_rescue';

    public const KEY_RIDER_UNCLAIMED_AGE_WARN_MINUTES = 'delivery.rider_unclaimed_age_warn_minutes';

    /** When true, kitchen can dispatch empty boxes to warehouse via a rider (N5). Default false = direct teleport. */
    public const KEY_KITCHEN_TO_OPS_VIA_RIDER = 'delivery.kitchen_to_ops_via_rider';

    /** Inclusive food VAT % (default 5). Snapshot onto orders at place. */
    public const KEY_VAT_RATE_PCT = 'finance.vat_rate_pct';

    /** Inclusive delivery VAT % (default 15). Applied to net delivery after delivery coupon. */
    public const KEY_DELIVERY_VAT_RATE_PCT = 'finance.delivery_vat_rate_pct';

    /** JSON map of EPS sub-gateway → fee % e.g. {"bank":1.5,"bkash":1.8}. */
    public const KEY_EPS_FEE_RATES = 'finance.eps_fee_rates_json';

    /** Prefer this middo_bank_accounts.id for EPS settlements. */
    public const KEY_DEFAULT_EPS_BANK_ACCOUNT_ID = 'finance.default_eps_bank_account_id';

    /**
     * Full (100%) prepayment is required when projected active orders reach this count.
     * Default 3 → COD allowed for 1–2 active orders only.
     */
    public const KEY_FULL_PREPAY_FROM_ACTIVE_ORDERS = 'order.full_prepay_from_active_orders';

    /** @var list<array{key: string, old: mixed, new: mixed}>|null */
    protected static ?array $auditBuffer = null;

    protected static function tierDefaultKey(string $tier): string
    {
        return 'kitchen.tier_defaults.'.KitchenTier::normalize($tier).'.allowed_open_groups';
    }

    protected static function deliveryCommissionKey(string $runType): string
    {
        return 'delivery.commission.'.$runType;
    }

    public static function beginAudit(): void
    {
        self::$auditBuffer = [];
    }

    /**
     * @return list<array{key: string, old: mixed, new: mixed}>
     */
    public static function takeAuditChanges(): array
    {
        $changes = self::$auditBuffer ?? [];
        self::$auditBuffer = null;

        return $changes;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! self::tableReady()) {
            return $default;
        }

        return Cache::remember(self::cacheKey($key), 60, function () use ($key, $default) {
            $row = Setting::query()->find($key);

            return $row?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        $old = self::tableReady()
            ? Setting::query()->whereKey($key)->value('value')
            : null;
        $new = $value === null ? null : (string) $value;

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $new]
        );

        Cache::forget(self::cacheKey($key));

        if (self::$auditBuffer !== null && (string) ($old ?? '') !== (string) ($new ?? '')) {
            self::$auditBuffer[] = [
                'key' => $key,
                'old' => $old,
                'new' => $new,
            ];
        }
    }

    public static function acceptWindowMinutes(): int
    {
        return max(1, (int) self::get(
            self::KEY_ACCEPT_WINDOW_MINUTES,
            config('middo.accept_window_minutes', 120)
        ));
    }

    public static function acceptWindowWarnMinutes(): int
    {
        $window = self::acceptWindowMinutes();
        $warn = max(1, (int) self::get(
            self::KEY_ACCEPT_WINDOW_WARN_MINUTES,
            config('middo.accept_window_warn_minutes', 15)
        ));

        return min($warn, $window);
    }

    /**
     * Accept-window open clock time as H:i (24h), Asia/Dhaka on the delivery day.
     * Empty/null falls back to "delivery time − accept_window_minutes".
     */
    public static function acceptWindowStartsAt(): ?string
    {
        $raw = trim((string) self::get(
            self::KEY_ACCEPT_WINDOW_STARTS_AT,
            config('middo.accept_window_starts_at', '10:00')
        ));

        if ($raw === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($raw, 'Asia/Dhaka')->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Human label for the configured start time, e.g. "10:00 AM".
     */
    public static function acceptWindowStartsAtLabel(): ?string
    {
        $time = self::acceptWindowStartsAt();
        if ($time === null) {
            return null;
        }

        return \Carbon\Carbon::parse($time, 'Asia/Dhaka')->format('g:i A');
    }

    /**
     * Daily order cutoff as H:i (24h, Asia/Dhaka).
     */
    public static function orderCutoffTime(): string
    {
        $defaultHour = (int) config('middo.order_cutoff_hour', 15);
        $defaultMinute = (int) config('middo.order_cutoff_minute', 28);
        $fallback = sprintf('%02d:%02d', $defaultHour, $defaultMinute);

        $raw = trim((string) self::get(self::KEY_ORDER_CUTOFF_TIME, $fallback));
        if ($raw === '') {
            return $fallback;
        }

        try {
            return \Carbon\Carbon::parse($raw, 'Asia/Dhaka')->format('H:i');
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * Default max meals per corporate per delivery date (overridable per user).
     */
    public static function maxOrderQtyAllowed(): int
    {
        return max(1, (int) self::get(
            self::KEY_MAX_ORDER_QTY_ALLOWED,
            config('middo.max_order_qty_allowed', 5)
        ));
    }

    /**
     * Minutes since kitchen dispatch before an unclaimed packed order is "aging".
     */
    public static function riderUnclaimedAgeWarnMinutes(): int
    {
        return max(1, (int) self::get(
            self::KEY_RIDER_UNCLAIMED_AGE_WARN_MINUTES,
            config('middo.rider_unclaimed_age_warn_minutes', 30)
        ));
    }

    public static function autoGroupQuantity(): int
    {
        return max(1, (int) self::get(
            self::KEY_AUTO_GROUP_QUANTITY,
            config('middo.auto_meal_group_quantity', 10)
        ));
    }

    public static function defaultAllowedOpenGroupsForTier(string $tier): int
    {
        $tier = KitchenTier::normalize($tier);
        $configDefault = (int) config("middo.kitchen_tier_defaults.{$tier}", match ($tier) {
            KitchenTier::GOLD => 2,
            KitchenTier::PLATINUM => 3,
            default => 1,
        });

        return max(0, (int) self::get(self::tierDefaultKey($tier), $configDefault));
    }

    /**
     * @return array{silver: int, gold: int, platinum: int}
     */
    public static function tierDefaults(): array
    {
        $out = [];
        foreach (KitchenTier::all() as $tier) {
            $out[$tier] = self::defaultAllowedOpenGroupsForTier($tier);
        }

        return $out;
    }

    /**
     * Default ৳ for a settings-backed delivery run type (not lunch/menu).
     */
    public static function deliveryCommissionDefault(string $runType): int
    {
        if (! DeliveryRunType::isSettingsBacked($runType)) {
            return 0;
        }

        $configDefault = (int) config(
            "middo.delivery_commission_defaults.{$runType}",
            0
        );

        return max(0, (int) self::get(self::deliveryCommissionKey($runType), $configDefault));
    }

    /**
     * @return array<string, int>
     */
    public static function deliveryCommissionDefaults(): array
    {
        $out = [];
        foreach (DeliveryRunType::settingsBacked() as $type) {
            $out[$type] = self::deliveryCommissionDefault($type);
        }

        return $out;
    }

    /**
     * Optional ৳ paid to rescue rider B on mid-run reassign (default 0 — no double lunch commission).
     */
    public static function midRunRescueCommission(): int
    {
        return max(0, (int) self::get(
            self::KEY_MID_RUN_RESCUE,
            config('middo.delivery_commission_defaults.mid_run_rescue', 0)
        ));
    }

    /**
     * Kitchen empty-box return via rider (kitchen→ops leg). Off = direct warehouse teleport.
     */
    public static function kitchenToOpsViaRider(): bool
    {
        $raw = self::get(
            self::KEY_KITCHEN_TO_OPS_VIA_RIDER,
            config('middo.kitchen_to_ops_via_rider', false) ? '1' : '0'
        );

        return in_array((string) $raw, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Inclusive VAT % applied to food only (charges excluded). Default 5.
     */
    public static function vatRatePct(): float
    {
        $raw = self::get(
            self::KEY_VAT_RATE_PCT,
            config('middo.vat_rate_pct', 5)
        );

        return max(0, min(100, (float) $raw));
    }

    /**
     * Inclusive VAT % on net delivery charge (after delivery coupon). Default 15.
     */
    public static function deliveryVatRatePct(): float
    {
        $raw = self::get(
            self::KEY_DELIVERY_VAT_RATE_PCT,
            config('middo.delivery_vat_rate_pct', 15)
        );

        return max(0, min(100, (float) $raw));
    }

    /**
     * @return array<string, float>
     */
    public static function epsFeeRates(): array
    {
        $defaults = config('middo.eps_fee_rate_defaults', []);
        $raw = self::get(self::KEY_EPS_FEE_RATES, null);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $map = is_array($decoded) ? $decoded : [];

        $out = [];
        foreach (EpsSubGateway::keys() as $key) {
            $value = $map[$key] ?? $defaults[$key] ?? 0;
            $out[$key] = max(0, min(100, (float) $value));
        }

        return $out;
    }

    public static function epsFeeRatePct(string $subGateway): float
    {
        $rates = self::epsFeeRates();
        $key = in_array($subGateway, EpsSubGateway::keys(), true) ? $subGateway : EpsSubGateway::OTHER;

        return $rates[$key] ?? $rates[EpsSubGateway::OTHER] ?? 0.0;
    }

    public static function defaultEpsBankAccountId(): ?int
    {
        $raw = self::get(self::KEY_DEFAULT_EPS_BANK_ACCOUNT_ID, null);
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * COD is allowed for up to this many projected meals (inclusive).
     * Full (100%) prepayment is required when projected meal quantity exceeds this.
     * Default 3 → COD for 1–3 meals; prepay from the 4th meal onward.
     */
    public static function fullPrepayFromActiveOrders(): int
    {
        return max(1, (int) self::get(
            self::KEY_FULL_PREPAY_FROM_ACTIVE_ORDERS,
            config('middo.full_prepay_from_active_orders', 3)
        ));
    }

    /**
     * Max projected meal quantity that may still use Cash on Delivery (inclusive).
     */
    public static function codMaxActiveOrders(): int
    {
        return self::fullPrepayFromActiveOrders();
    }

    /**
     * @param  array{accept_window_minutes?: int, accept_window_warn_minutes?: int, accept_window_starts_at?: string|null, order_cutoff_time?: string, max_order_qty_allowed?: int, auto_group_quantity?: int, tier_defaults?: array<string, int>, delivery_commissions?: array<string, int>, mid_run_rescue_commission?: int, kitchen_to_ops_via_rider?: bool, vat_rate_pct?: float|int, eps_fee_rates?: array<string, float|int>, default_eps_bank_account_id?: int|null, full_prepay_from_active_orders?: int}  $payload
     */
    public static function updateMealAndKitchenDefaults(array $payload): void
    {
        if (array_key_exists('accept_window_minutes', $payload)) {
            self::set(self::KEY_ACCEPT_WINDOW_MINUTES, max(1, (int) $payload['accept_window_minutes']));
        }

        if (array_key_exists('accept_window_warn_minutes', $payload)) {
            self::set(self::KEY_ACCEPT_WINDOW_WARN_MINUTES, max(1, (int) $payload['accept_window_warn_minutes']));
        }

        if (array_key_exists('accept_window_starts_at', $payload)) {
            $raw = trim((string) ($payload['accept_window_starts_at'] ?? ''));
            if ($raw === '') {
                self::set(self::KEY_ACCEPT_WINDOW_STARTS_AT, '');
            } else {
                try {
                    self::set(
                        self::KEY_ACCEPT_WINDOW_STARTS_AT,
                        \Carbon\Carbon::parse($raw, 'Asia/Dhaka')->format('H:i')
                    );
                } catch (\Throwable) {
                    throw new \InvalidArgumentException('Accept window start time is invalid.');
                }
            }
        }

        if (array_key_exists('order_cutoff_time', $payload)) {
            $raw = trim((string) ($payload['order_cutoff_time'] ?? ''));
            if ($raw === '') {
                throw new \InvalidArgumentException('Daily order cutoff time is required.');
            }
            try {
                self::set(
                    self::KEY_ORDER_CUTOFF_TIME,
                    \Carbon\Carbon::parse($raw, 'Asia/Dhaka')->format('H:i')
                );
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Daily order cutoff time is invalid.');
            }
        }

        if (array_key_exists('max_order_qty_allowed', $payload)) {
            self::set(self::KEY_MAX_ORDER_QTY_ALLOWED, max(1, (int) $payload['max_order_qty_allowed']));
        }

        if (array_key_exists('auto_group_quantity', $payload)) {
            self::set(self::KEY_AUTO_GROUP_QUANTITY, max(1, (int) $payload['auto_group_quantity']));
        }

        if (isset($payload['tier_defaults']) && is_array($payload['tier_defaults'])) {
            foreach (KitchenTier::all() as $tier) {
                if (! array_key_exists($tier, $payload['tier_defaults'])) {
                    continue;
                }
                self::set(
                    self::tierDefaultKey($tier),
                    max(0, (int) $payload['tier_defaults'][$tier])
                );
            }
        }

        if (isset($payload['delivery_commissions']) && is_array($payload['delivery_commissions'])) {
            foreach (DeliveryRunType::settingsBacked() as $type) {
                if (! array_key_exists($type, $payload['delivery_commissions'])) {
                    continue;
                }
                self::set(
                    self::deliveryCommissionKey($type),
                    max(0, (int) $payload['delivery_commissions'][$type])
                );
            }
        }

        if (array_key_exists('mid_run_rescue_commission', $payload)) {
            self::set(self::KEY_MID_RUN_RESCUE, max(0, (int) $payload['mid_run_rescue_commission']));
        }

        if (array_key_exists('kitchen_to_ops_via_rider', $payload)) {
            self::set(self::KEY_KITCHEN_TO_OPS_VIA_RIDER, $payload['kitchen_to_ops_via_rider'] ? '1' : '0');
        }

        if (array_key_exists('vat_rate_pct', $payload)) {
            $pct = max(0, min(100, (float) $payload['vat_rate_pct']));
            self::set(self::KEY_VAT_RATE_PCT, rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') ?: '0');
        }

        if (array_key_exists('delivery_vat_rate_pct', $payload)) {
            $pct = max(0, min(100, (float) $payload['delivery_vat_rate_pct']));
            self::set(self::KEY_DELIVERY_VAT_RATE_PCT, rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') ?: '0');
        }

        if (array_key_exists('eps_fee_rates', $payload) && is_array($payload['eps_fee_rates'])) {
            $clean = [];
            foreach (EpsSubGateway::keys() as $key) {
                if (! array_key_exists($key, $payload['eps_fee_rates'])) {
                    continue;
                }
                $clean[$key] = max(0, min(100, (float) $payload['eps_fee_rates'][$key]));
            }
            self::set(self::KEY_EPS_FEE_RATES, json_encode($clean, JSON_THROW_ON_ERROR));
        }

        if (array_key_exists('default_eps_bank_account_id', $payload)) {
            $id = $payload['default_eps_bank_account_id'];
            self::set(self::KEY_DEFAULT_EPS_BANK_ACCOUNT_ID, $id ? (string) (int) $id : null);
        }

        if (array_key_exists('full_prepay_from_active_orders', $payload)) {
            self::set(
                self::KEY_FULL_PREPAY_FROM_ACTIVE_ORDERS,
                max(1, (int) $payload['full_prepay_from_active_orders'])
            );
        }
    }

    protected static function cacheKey(string $key): string
    {
        return 'middo_setting:'.$key;
    }

    protected static function tableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
