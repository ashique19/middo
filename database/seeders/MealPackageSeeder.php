<?php

namespace Database\Seeders;

use App\Models\MealPackage;
use App\Models\MealPackageDay;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\OrderCutoff;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MealPackageSeeder extends Seeder
{
    public function run(): void
    {
        $menuItems = MenuItem::query()->orderBy('display_order')->orderBy('id')->get();

        if ($menuItems->isEmpty()) {
            $this->command?->warn('MealPackageSeeder: no menu items found — skipping.');

            return;
        }

        $vegItems = $menuItems->filter(function (MenuItem $item) {
            $haystack = strtolower($item->name.' '.$item->summary);

            return str_contains($haystack, 'veg') || str_contains($haystack, 'khichdi');
        })->values();

        if ($vegItems->isEmpty()) {
            $vegItems = $menuItems;
        }

        $adminId = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->value('id');

        $start = now(OrderCutoff::timezone())->addDay()->startOfDay();

        $plans = [
            [
                'name' => '৳79 / day · Classic',
                'summary' => 'Affordable 30-day office lunch plan with a rotating Middo thali each weekday.',
                'price_per_day' => 79,
                'diet_tag' => 'classic',
                'display_order' => 1,
                'pool' => $menuItems,
            ],
            [
                'name' => '৳150 / day · Standard',
                'summary' => 'Balanced month-long package — fuller portions and a wider daily rotation.',
                'price_per_day' => 150,
                'diet_tag' => 'classic',
                'display_order' => 2,
                'pool' => $menuItems,
            ],
            [
                'name' => '৳200 / day · Premium',
                'summary' => 'Premium 30-day plan featuring Middo’s richer thalis for executive teams.',
                'price_per_day' => 200,
                'diet_tag' => 'protein',
                'display_order' => 3,
                'pool' => $menuItems,
            ],
            [
                'name' => 'Vegetarian · 30 days',
                'summary' => 'Vegetarian-focused month package. Ideal when the office wants meat-free lunches.',
                'price_per_day' => 120,
                'diet_tag' => 'vegetarian',
                'display_order' => 4,
                'pool' => $vegItems,
            ],
        ];

        foreach ($plans as $plan) {
            $package = MealPackage::updateOrCreate(
                [
                    'name' => $plan['name'],
                    'start_date' => $start->toDateString(),
                ],
                [
                    'summary' => $plan['summary'],
                    'price_per_day' => $plan['price_per_day'],
                    'diet_tag' => $plan['diet_tag'],
                    'duration_days' => 30,
                    'end_date' => $start->copy()->addDays(29)->toDateString(),
                    'status' => MealPackage::STATUS_PUBLISHED,
                    'display_order' => $plan['display_order'],
                    'thumbnail' => $plan['pool']->first()?->thumbnail,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]
            );

            MealPackageDay::query()->where('meal_package_id', $package->id)->delete();

            $pool = $plan['pool']->values();
            $poolCount = $pool->count();

            for ($i = 0; $i < 30; $i++) {
                /** @var Carbon $date */
                $date = $start->copy()->addDays($i);
                $menuItem = $pool[$i % $poolCount];

                MealPackageDay::create([
                    'meal_package_id' => $package->id,
                    'delivery_date' => $date->toDateString(),
                    'menu_item_id' => $menuItem->id,
                ]);
            }

            $this->command?->info("Seeded package: {$package->name} ({$package->start_date->toDateString()} → {$package->end_date->toDateString()})");
        }
    }
}
