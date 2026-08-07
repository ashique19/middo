<?php

namespace App\Support;

class BdBanks
{
    /** @var list<array{name: string, cities: list<array{city: string, branches: list<string>}>}>|null */
    protected static ?array $catalog = null;

    /**
     * @return list<array{name: string, cities: list<array{city: string, branches: list<string>}>}>
     */
    public static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $path = resource_path('data/bd_banks.json');
        if (! is_readable($path)) {
            return self::$catalog = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        self::$catalog = is_array($decoded) ? $decoded : [];

        return self::$catalog;
    }

    /**
     * @return list<string>
     */
    public static function bankNames(): array
    {
        return array_values(array_map(
            static fn (array $bank): string => (string) ($bank['name'] ?? ''),
            self::catalog()
        ));
    }

    /**
     * @return list<string>
     */
    public static function citiesFor(string $bankName): array
    {
        $bank = self::findBank($bankName);
        if ($bank === null) {
            return [];
        }

        return array_values(array_map(
            static fn (array $city): string => (string) ($city['city'] ?? ''),
            $bank['cities'] ?? []
        ));
    }

    /**
     * @return list<string>
     */
    public static function branchesFor(string $bankName, string $cityName): array
    {
        $bank = self::findBank($bankName);
        if ($bank === null) {
            return [];
        }

        foreach ($bank['cities'] ?? [] as $city) {
            if (strcasecmp((string) ($city['city'] ?? ''), $cityName) === 0) {
                return array_values(array_map('strval', $city['branches'] ?? []));
            }
        }

        return [];
    }

    public static function isValidSelection(string $bankName, string $cityName, string $branchName): bool
    {
        if ($bankName === '' || $cityName === '' || $branchName === '') {
            return false;
        }

        $branches = self::branchesFor($bankName, $cityName);
        foreach ($branches as $branch) {
            if (strcasecmp($branch, $branchName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{name: string, cities: list<array{city: string, branches: list<string>}>}|null
     */
    protected static function findBank(string $bankName): ?array
    {
        if ($bankName === '') {
            return null;
        }

        foreach (self::catalog() as $bank) {
            if (strcasecmp((string) ($bank['name'] ?? ''), $bankName) === 0) {
                return $bank;
            }
        }

        return null;
    }
}
