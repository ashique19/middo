<?php

namespace Database\Seeders;

use App\Models\MealPackage;
use App\Models\MealPackageDay;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\OrderCutoff;
use Illuminate\Database\Seeder;

/**
 * Seeds published package rate plans (price/day + diet). Corporates build monthly
 * packages by selecting menus + day counts; operations assigns exact dates later.
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

        $plans = [
            [
                'name' => '৳79 / day · Classic',
                'summary' => 'Affordable monthly office lunch plan. Pick which Middo menus you want and how many days each month — operations schedules the dates.',
                'price_per_day' => 79,
                'diet_tag' => 'classic',
                'display_order' => 1,
            ],
            [
                'name' => '৳150 / day · Standard',
                'summary' => 'Balanced monthly package rate. Choose your menus and day counts; weekdays you omit are never billed.',
                'price_per_day' => 150,
                'diet_tag' => 'classic',
                'display_order' => 2,
            ],
            [
                'name' => '৳200 / day · Premium',
                'summary' => 'Premium monthly plan for executive teams. Select menus and days, prepay, then Middo operations locks in the calendar.',
                'price_per_day' => 200,
                'diet_tag' => 'protein',
                'display_order' => 3,
            ],
            [
                'name' => 'Vegetarian · monthly',
                'summary' => 'Vegetarian-focused monthly package rate. Ideal when the office wants meat-free lunches on selected days.',
                'price_per_day' => 120,
                'diet_tag' => 'vegetarian',
                'display_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            $package = MealPackage::updateOrCreate(
                [
                    'name' => $plan['name'],
                ],
                [
                    'summary' => $plan['summary'],
                    'price_per_day' => $plan['price_per_day'],
                    'diet_tag' => $plan['diet_tag'],
                    'duration_days' => 30,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => MealPackage::STATUS_PUBLISHED,
                    'display_order' => $plan['display_order'],
                    'thumbnail' => $menuItems->first()?->thumbnail,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]
            );

            // Rate plans no longer ship a fixed calendar of meal_package_days.
            MealPackageDay::query()->where('meal_package_id', $package->id)->delete();

            $this->command?->info("Seeded package rate plan: {$package->name}");
        }
    }
}
