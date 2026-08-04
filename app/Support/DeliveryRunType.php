<?php

namespace App\Support;

/**
 * Delivery / rider run types for commissions.
 *
 * Lunch (kitchen→corporate) uses menu.delivery_commission — not settings.
 * Box / custom runs use MiddoSettings defaults (+ optional per-rider overrides).
 */
class DeliveryRunType
{
    public const KITCHEN_TO_CORPORATE = 'kitchen_to_corporate';

    public const CORPORATE_TO_KITCHEN = 'corporate_to_kitchen';

    public const KITCHEN_TO_OPS = 'kitchen_to_ops';

    public const OPS_TO_KITCHEN = 'ops_to_kitchen';

    public const CUSTOM = 'custom';

    /**
     * Run types whose default rate lives in admin Settings (not menu).
     *
     * @return list<string>
     */
    public static function settingsBacked(): array
    {
        return [
            self::CORPORATE_TO_KITCHEN,
            self::KITCHEN_TO_OPS,
            self::OPS_TO_KITCHEN,
            self::CUSTOM,
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::KITCHEN_TO_CORPORATE,
            ...self::settingsBacked(),
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    public static function isSettingsBacked(string $type): bool
    {
        return in_array($type, self::settingsBacked(), true);
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::KITCHEN_TO_CORPORATE => 'Kitchen → corporate (lunch)',
            self::CORPORATE_TO_KITCHEN => 'Corporate → kitchen (box)',
            self::KITCHEN_TO_OPS => 'Kitchen → ops (box)',
            self::OPS_TO_KITCHEN => 'Ops → kitchen (box)',
            self::CUSTOM => 'Custom point → point',
            default => $type,
        };
    }
}
