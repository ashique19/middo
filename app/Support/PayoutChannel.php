<?php

namespace App\Support;

class PayoutChannel
{
    public const CASH = 'cash';

    public const BANK = 'bank';

    public const BKASH = 'bkash';

    public const NAGAD = 'nagad';

    public const ACCOUNT_NAME_PATTERN = '/^[A-Za-z.\-\s]+$/';

    public const ACCOUNT_NUMBER_PATTERN = '/^\d+$/';

    public const PERSONAL_MOBILE_PATTERN = '/^01[3-9]\d{8}$/';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::CASH, self::BANK, self::BKASH, self::NAGAD];
    }

    /**
     * Channels kitchen/delivery may choose when requesting a withdrawal.
     *
     * @return list<string>
     */
    public static function partnerChannels(): array
    {
        return [self::BANK, self::BKASH, self::NAGAD];
    }

    public static function defaultPartnerChannel(): string
    {
        return self::BANK;
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
        foreach (['account_name', 'account_number', 'bank_name', 'city', 'branch', 'mobile'] as $key) {
            $value = trim((string) ($details[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = mb_substr($value, 0, 120);
            }
        }

        if (isset($clean['account_number'])) {
            $clean['account_number'] = preg_replace('/\D+/', '', $clean['account_number']) ?: '';
            if ($clean['account_number'] === '') {
                unset($clean['account_number']);
            }
        }

        if (isset($clean['mobile'])) {
            $clean['mobile'] = preg_replace('/\D+/', '', $clean['mobile']) ?: '';
            if ($clean['mobile'] === '') {
                unset($clean['mobile']);
            }
        }

        return match ($channel) {
            self::BANK => array_intersect_key($clean, array_flip([
                'bank_name', 'city', 'branch', 'account_name', 'account_number',
            ])),
            self::BKASH, self::NAGAD => array_intersect_key($clean, array_flip(['mobile'])),
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
            foreach (['bank_name' => 'Bank', 'city' => 'City', 'branch' => 'Branch', 'account_name' => 'Account name', 'account_number' => 'Account number'] as $key => $label) {
                if (($normalized[$key] ?? '') === '') {
                    throw new \InvalidArgumentException($label.' is required.');
                }
            }

            if (! preg_match(self::ACCOUNT_NAME_PATTERN, $normalized['account_name'])) {
                throw new \InvalidArgumentException('Account name may only contain letters, spaces, dots, and hyphens.');
            }

            if (! preg_match(self::ACCOUNT_NUMBER_PATTERN, $normalized['account_number'])) {
                throw new \InvalidArgumentException('Account number must be digits only.');
            }

            if (! BdBanks::isValidSelection(
                $normalized['bank_name'],
                $normalized['city'],
                $normalized['branch']
            )) {
                throw new \InvalidArgumentException('Select a valid bank, city, and branch.');
            }
        }

        if (in_array($channel, [self::BKASH, self::NAGAD], true)) {
            $mobile = $normalized['mobile'] ?? '';
            if ($mobile === '') {
                throw new \InvalidArgumentException(self::label($channel).' personal number is required.');
            }
            if (! preg_match(self::PERSONAL_MOBILE_PATTERN, $mobile)) {
                throw new \InvalidArgumentException('Provide a valid 11-digit personal number (e.g., 01710123456).');
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
                $details['city'] ?? null,
                $details['branch'] ?? null,
                $details['account_name'] ?? null,
                $details['account_number'] ?? null,
            ]),
            self::BKASH, self::NAGAD => array_filter([
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
        $preferred = (string) ($methods['preferred'] ?? self::defaultPartnerChannel());
        if (! in_array($preferred, self::partnerChannels(), true)) {
            $preferred = self::defaultPartnerChannel();
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
