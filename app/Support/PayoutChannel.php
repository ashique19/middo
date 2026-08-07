<?php

namespace App\Support;

class PayoutChannel
{
    public const CASH = 'cash';

    public const BANK = 'bank';

    public const BKASH = 'bkash';

    public const NAGAD = 'nagad';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::CASH, self::BANK, self::BKASH, self::NAGAD];
    }

    public static function label(string $channel): string
    {
        return match ($channel) {
            self::CASH => 'Cash',
            self::BANK => 'Bank',
            self::BKASH => 'bKash',
            self::NAGAD => 'Nagad',
            default => ucfirst($channel),
        };
    }

    public static function usesBankFloat(string $channel): bool
    {
        return in_array($channel, [self::BANK, self::BKASH, self::NAGAD], true);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    public static function normalizeDetails(string $channel, array $details): array
    {
        $clean = [];
        foreach (['account_name', 'account_number', 'bank_name', 'mobile'] as $key) {
            $value = trim((string) ($details[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = mb_substr($value, 0, 120);
            }
        }

        return match ($channel) {
            self::BANK => array_intersect_key($clean, array_flip(['account_name', 'account_number', 'bank_name'])),
            self::BKASH, self::NAGAD => array_intersect_key($clean, array_flip(['account_name', 'mobile'])),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function assertValid(string $channel, array $details): void
    {
        if (! in_array($channel, self::all(), true)) {
            throw new \InvalidArgumentException('Invalid payout channel.');
        }

        $normalized = self::normalizeDetails($channel, $details);

        if ($channel === self::BANK) {
            if (($normalized['account_number'] ?? '') === '') {
                throw new \InvalidArgumentException('Bank account number is required.');
            }
        }

        if (in_array($channel, [self::BKASH, self::NAGAD], true)) {
            if (($normalized['mobile'] ?? '') === '') {
                throw new \InvalidArgumentException(self::label($channel).' mobile number is required.');
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function detailsSummary(string $channel, ?array $details): string
    {
        $details = is_array($details) ? $details : [];
        $parts = match ($channel) {
            self::BANK => array_filter([
                $details['bank_name'] ?? null,
                $details['account_name'] ?? null,
                $details['account_number'] ?? null,
            ]),
            self::BKASH, self::NAGAD => array_filter([
                $details['account_name'] ?? null,
                $details['mobile'] ?? null,
            ]),
            default => ['Cash payout'],
        };

        return implode(' · ', $parts) ?: self::label($channel);
    }

    /**
     * Channels that store destination details on the user profile.
     *
     * @return list<string>
     */
    public static function profileStored(): array
    {
        return [self::BANK, self::BKASH, self::NAGAD];
    }

    public static function requiresProfileDetails(string $channel): bool
    {
        return in_array($channel, self::profileStored(), true);
    }

    /**
     * Normalize a full payout_methods profile payload.
     *
     * @param  array<string, mixed>  $methods
     * @return array<string, mixed>
     */
    public static function normalizeProfileMethods(array $methods): array
    {
        $preferred = (string) ($methods['preferred'] ?? self::CASH);
        if (! in_array($preferred, self::all(), true)) {
            $preferred = self::CASH;
        }

        $normalized = ['preferred' => $preferred];

        foreach (self::profileStored() as $channel) {
            $raw = is_array($methods[$channel] ?? null) ? $methods[$channel] : [];
            $details = self::normalizeDetails($channel, $raw);
            if ($details !== []) {
                $normalized[$channel] = $details;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $methods
     * @return array<string, string>
     */
    public static function detailsFromProfile(?array $methods, string $channel): array
    {
        if ($channel === self::CASH) {
            return [];
        }

        $methods = is_array($methods) ? $methods : [];
        $raw = is_array($methods[$channel] ?? null) ? $methods[$channel] : [];

        return self::normalizeDetails($channel, $raw);
    }

    /**
     * @param  array<string, mixed>|null  $methods
     */
    public static function profileHasCompleteDetails(?array $methods, string $channel): bool
    {
        if (! self::requiresProfileDetails($channel)) {
            return true;
        }

        try {
            self::assertValid($channel, self::detailsFromProfile($methods, $channel));

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
