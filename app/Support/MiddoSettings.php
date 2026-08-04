<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MiddoSettings
{
    public const KEY_ACCEPT_WINDOW_MINUTES = 'meal.accept_window_minutes';

    public const KEY_AUTO_GROUP_QUANTITY = 'meal.auto_group_quantity';

    protected static function tierDefaultKey(string $tier): string
    {
        return 'kitchen.tier_defaults.'.KitchenTier::normalize($tier).'.allowed_open_groups';
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
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value]
        );

        Cache::forget(self::cacheKey($key));
    }

    public static function acceptWindowMinutes(): int
    {
        return max(1, (int) self::get(
            self::KEY_ACCEPT_WINDOW_MINUTES,
            config('middo.accept_window_minutes', 120)
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
     * @param  array{accept_window_minutes?: int, auto_group_quantity?: int, tier_defaults?: array<string, int>}  $payload
     */
    public static function updateMealAndKitchenDefaults(array $payload): void
    {
        if (array_key_exists('accept_window_minutes', $payload)) {
            self::set(self::KEY_ACCEPT_WINDOW_MINUTES, max(1, (int) $payload['accept_window_minutes']));
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
