<?php

namespace App\Support;

/**
 * Normalize EPS verify payload into a Middo sub-gateway key for fee lookup.
 */
class EpsSubGateway
{
    public const BANK = 'bank';

    public const BKASH = 'bkash';

    public const NAGAD = 'nagad';

    public const ROCKET = 'rocket';

    public const CARD = 'card';

    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::BANK, self::BKASH, self::NAGAD, self::ROCKET, self::CARD, self::OTHER];
    }

    /**
     * @param  array<string, mixed>  $epsRaw
     */
    public static function fromEpsRaw(array $epsRaw): string
    {
        $candidates = [
            $epsRaw['FinancialEntityName'] ?? null,
            $epsRaw['financialEntityName'] ?? null,
            $epsRaw['PaymentMethod'] ?? null,
            $epsRaw['paymentMethod'] ?? null,
            $epsRaw['PaymentType'] ?? null,
            $epsRaw['paymentType'] ?? null,
            $epsRaw['IssuerName'] ?? null,
            $epsRaw['issuerName'] ?? null,
            $epsRaw['CardType'] ?? null,
            $epsRaw['Gateway'] ?? null,
        ];

        $entityId = $epsRaw['FinancialEntityId'] ?? $epsRaw['financialEntityId'] ?? null;
        if ($entityId !== null && (int) $entityId !== 0) {
            $candidates[] = 'entity:'.$entityId;
        }

        $haystack = strtolower(implode(' ', array_map(
            fn ($v) => is_scalar($v) ? (string) $v : '',
            $candidates
        )));

        if ($haystack === '' || trim($haystack) === '') {
            return self::OTHER;
        }

        if (str_contains($haystack, 'bkash') || str_contains($haystack, 'bKash')) {
            return self::BKASH;
        }
        if (str_contains($haystack, 'nagad')) {
            return self::NAGAD;
        }
        if (str_contains($haystack, 'rocket')) {
            return self::ROCKET;
        }
        if (str_contains($haystack, 'card') || str_contains($haystack, 'visa') || str_contains($haystack, 'master')) {
            return self::CARD;
        }
        if (str_contains($haystack, 'bank') || str_contains($haystack, 'nexus') || str_contains($haystack, 'ach')) {
            return self::BANK;
        }

        return self::OTHER;
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::BANK => 'EPS → Bank',
            self::BKASH => 'EPS → bKash',
            self::NAGAD => 'EPS → Nagad',
            self::ROCKET => 'EPS → Rocket',
            self::CARD => 'EPS → Card',
            default => 'EPS → Other',
        };
    }
}
