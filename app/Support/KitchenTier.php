<?php

namespace App\Support;

class KitchenTier
{
    public const SILVER = 'silver';

    public const GOLD = 'gold';

    public const PLATINUM = 'platinum';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::SILVER,
            self::GOLD,
            self::PLATINUM,
        ];
    }

    public static function isValid(?string $tier): bool
    {
        return $tier !== null && in_array($tier, self::all(), true);
    }

    public static function normalize(?string $tier): string
    {
        return self::isValid($tier) ? $tier : self::SILVER;
    }

    public static function label(string $tier): string
    {
        return match ($tier) {
            self::GOLD => 'Gold',
            self::PLATINUM => 'Platinum',
            default => 'Silver',
        };
    }
}
