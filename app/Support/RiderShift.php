<?php

namespace App\Support;

/**
 * Rider availability for accepting new runs (N1).
 * Separate from account status (active/inactive).
 */
class RiderShift
{
    public const ON = 'on';

    public const OFF = 'off';

    public const UNABLE = 'unable';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ON, self::OFF, self::UNABLE];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function normalize(?string $status): string
    {
        $status = $status ?: self::ON;

        return self::isValid($status) ? $status : self::ON;
    }

    public static function canAcceptNewRuns(?string $status): bool
    {
        return self::normalize($status) === self::ON;
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            self::ON => 'On shift',
            self::OFF => 'Off shift',
            self::UNABLE => 'Unable to continue',
            default => $status,
        };
    }
}
