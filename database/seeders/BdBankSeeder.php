<?php

namespace Database\Seeders;

use App\Models\BdBank;
use App\Models\BdBankBranch;
use App\Models\BdBankCity;
use App\Support\BdBanks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BdBankSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/bd_banks.json');
        if (! is_readable($path)) {
            $this->command?->warn('database/data/bd_banks.json missing — skipping BdBankSeeder.');

            return;
        }

        $catalog = json_decode((string) file_get_contents($path), true);
        if (! is_array($catalog) || $catalog === []) {
            $this->command?->warn('bd_banks.json empty or invalid — skipping.');

            return;
        }

        DB::transaction(function () use ($catalog) {
            foreach ($catalog as $bankRow) {
                $bankName = trim((string) ($bankRow['name'] ?? ''));
                if ($bankName === '') {
                    continue;
                }

                $bank = BdBank::query()->updateOrCreate(
                    ['name' => $bankName],
                    ['is_active' => true]
                );

                foreach ($bankRow['cities'] ?? [] as $cityRow) {
                    $cityName = trim((string) ($cityRow['city'] ?? ''));
                    if ($cityName === '') {
                        continue;
                    }

                    $city = BdBankCity::query()->updateOrCreate(
                        [
                            'bd_bank_id' => $bank->id,
                            'name' => $cityName,
                        ],
                        []
                    );

                    $branchRows = [];
                    $now = now();
                    $seen = [];
                    foreach ($cityRow['branches'] ?? [] as $branchName) {
                        $branchName = trim((string) $branchName);
                        if ($branchName === '') {
                            continue;
                        }
                        $key = mb_strtolower($branchName);
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        $branchRows[] = [
                            'bd_bank_city_id' => $city->id,
                            'name' => $branchName,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($branchRows === []) {
                        continue;
                    }

                    // Insert missing branches only (idempotent re-seed).
                    $existing = BdBankBranch::query()
                        ->where('bd_bank_city_id', $city->id)
                        ->pluck('name')
                        ->map(fn ($n) => mb_strtolower((string) $n))
                        ->all();
                    $existingLookup = array_fill_keys($existing, true);

                    $toInsert = array_values(array_filter(
                        $branchRows,
                        fn (array $row) => ! isset($existingLookup[mb_strtolower($row['name'])])
                    ));

                    foreach (array_chunk($toInsert, 500) as $chunk) {
                        BdBankBranch::query()->insert($chunk);
                    }
                }
            }
        });

        BdBanks::forgetCache();
        $this->command?->info('BD banks catalog seeded ('.BdBank::query()->count().' banks).');
    }
}
