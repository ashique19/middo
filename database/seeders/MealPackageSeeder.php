<?php

namespace Database\Seeders;

use App\Models\MealPackage;
use App\Models\MealPackageDay;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\OrderCutoff;
use Illuminate\Database\Seeder;

/**
 * Seeds the single published monthly pack. Corporates build the month by mixing
 * any menus + day counts (billed at each menu's price). Operations confirms dates.
 */
class MealPackageSeeder extends Seeder
{
    public function run(): void
    {
        $menuItems = MenuItem::query()->orderBy('display_order')->orderBy('id')->get();

        if ($menuItems->isEmpty()) {
            $this->command?->warn('MealPackageSeeder: no menu items found — skipping.');

            return;
        }

        $adminId = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->value('id');

        $start = now(OrderCutoff::timezone())->startOfMonth()->startOfDay();
        $end = $start->copy()->addYear()->endOfMonth()->startOfDay();

        // Archive legacy multi-rate plans from older seeds.
        MealPackage::query()
            ->where('name', '!=', 'Monthly Pack')
            ->where('status', MealPackage::STATUS_PUBLISHED)
            ->update(['status' => MealPackage::STATUS_ARCHIVED]);

        $package = MealPackage::updateOrCreate(
            ['name' => 'Monthly Pack'],
            [
                'summary' => 'Build your month: mix any Middo menus and day counts. Your bill is the sum of each menu price × days × seats. Apply a coupon at checkout. Operations confirms delivery dates in one or more batches.',
                'price_per_day' => 0,
                'diet_tag' => 'classic',
                'duration_days' => 30,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => MealPackage::STATUS_PUBLISHED,
                'display_order' => 1,
                'thumbnail' => $menuItems->first()?->thumbnail,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]
        );

        MealPackageDay::query()->where('meal_package_id', $package->id)->delete();

        $this->command?->info('MealPackageSeeder: published single Monthly Pack #'.$package->id);
    }
}
