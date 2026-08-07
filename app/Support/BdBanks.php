<?php

namespace App\Support;

use App\Models\BdBank;
use App\Models\BdBankBranch;
use App\Models\BdBankCity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BdBanks
{
    public const CACHE_KEY = 'bd_banks.catalog.v1';

    /**
     * @return list<array{name: string, cities: list<array{city: string, branches: list<string>}>}>
     */
    public static function catalog(): array
    {
        return Cache::remember(self::CACHE_KEY, 600, function () {
            if (self::databaseReady() && BdBank::query()->exists()) {
                return self::catalogFromDatabase();
            }

            return self::catalogFromJson();
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
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

        foreach (self::branchesFor($bankName, $cityName) as $branch) {
            if (strcasecmp($branch, $branchName) === 0) {
                return true;
            }
        }

        return false;
    }

    protected static function databaseReady(): bool
    {
        return Schema::hasTable('bd_banks')
            && Schema::hasTable('bd_bank_cities')
            && Schema::hasTable('bd_bank_branches');
    }

    /**
     * @return list<array{name: string, cities: list<array{city: string, branches: list<string>}>}>
     */
    protected static function catalogFromDatabase(): array
    {
        $banks = BdBank::query()
            ->where('is_active', true)
            ->with(['cities.branches' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        $catalog = [];
        foreach ($banks as $bank) {
            $cities = [];
            foreach ($bank->cities as $city) {
                $branches = $city->branches->pluck('name')->filter()->values()->all();
                if ($branches === []) {
                    continue;
                }
                $cities[] = [
                    'city' => (string) $city->name,
                    'branches' => $branches,
                ];
            }
            if ($cities === []) {
                continue;
            }
            $catalog[] = [
                'name' => (string) $bank->name,
                'cities' => $cities,
            ];
        }

        return $catalog;
    }

    /**
     * @return list<array{name: string, cities: list<array{city: string, branches: list<string>}>}>
     */
    protected static function catalogFromJson(): array
    {
        foreach ([database_path('data/bd_banks.json'), resource_path('data/bd_banks.json')] as $path) {
            if (! is_readable($path)) {
                continue;
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
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
