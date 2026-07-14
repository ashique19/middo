<?php

namespace Database\Seeders;

use App\Models\MealItem;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MealItemTestSeeder extends Seeder
{
    public function run(): void
    {
        $menus = MenuItem::orderBy('display_order')->get();

        if ($menus->isEmpty()) {
            $this->command?->warn('No menu items found. Run MenuItemSeeder first.');

            return;
        }

        DB::table('recipe_photos')->delete();
        DB::table('recipe_ingredients')->delete();
        DB::table('recipes')->delete();
        DB::table('menu_item_meal_item')->delete();
        DB::table('meal_items')->delete();

        $library = [
            'Rice' => [
                'summary' => 'Steamed basmati rice.',
                'other_costs' => 5,
                'ingredients' => [
                    ['name' => 'Basmati rice', 'quantity' => 0.15, 'unit' => 'kg', 'cost' => 18],
                    ['name' => 'Salt', 'quantity' => 2, 'unit' => 'g', 'cost' => 1],
                ],
            ],
            'Daal' => [
                'summary' => 'Yellow lentil dal.',
                'other_costs' => 8,
                'ingredients' => [
                    ['name' => 'Masoor dal', 'quantity' => 0.08, 'unit' => 'kg', 'cost' => 14],
                    ['name' => 'Onion', 'quantity' => 0.05, 'unit' => 'kg', 'cost' => 4],
                    ['name' => 'Spices', 'quantity' => 1, 'unit' => 'pcs', 'cost' => 6],
                ],
            ],
            'Potato Mash' => [
                'summary' => 'Spiced mashed potatoes.',
                'other_costs' => 6,
                'ingredients' => [
                    ['name' => 'Potato', 'quantity' => 0.2, 'unit' => 'kg', 'cost' => 10],
                    ['name' => 'Butter', 'quantity' => 0.02, 'unit' => 'kg', 'cost' => 8],
                ],
            ],
            'Salad' => [
                'summary' => 'Fresh kachumber salad.',
                'other_costs' => 3,
                'ingredients' => [
                    ['name' => 'Cucumber', 'quantity' => 0.05, 'unit' => 'kg', 'cost' => 5],
                    ['name' => 'Tomato', 'quantity' => 0.05, 'unit' => 'kg', 'cost' => 6],
                    ['name' => 'Onion', 'quantity' => 0.03, 'unit' => 'kg', 'cost' => 3],
                ],
            ],
            'Chicken Curry' => [
                'summary' => 'Rich chicken curry.',
                'other_costs' => 15,
                'ingredients' => [
                    ['name' => 'Chicken', 'quantity' => 0.18, 'unit' => 'kg', 'cost' => 55],
                    ['name' => 'Onion', 'quantity' => 0.08, 'unit' => 'kg', 'cost' => 5],
                    ['name' => 'Oil & spices', 'quantity' => 1, 'unit' => 'pcs', 'cost' => 12],
                ],
            ],
            'Beef Curry' => [
                'summary' => 'Slow-cooked beef curry.',
                'other_costs' => 18,
                'ingredients' => [
                    ['name' => 'Beef', 'quantity' => 0.18, 'unit' => 'kg', 'cost' => 70],
                    ['name' => 'Onion', 'quantity' => 0.08, 'unit' => 'kg', 'cost' => 5],
                    ['name' => 'Oil & spices', 'quantity' => 1, 'unit' => 'pcs', 'cost' => 14],
                ],
            ],
            'Mixed Veg' => [
                'summary' => 'Seasonal mixed vegetable curry.',
                'other_costs' => 7,
                'ingredients' => [
                    ['name' => 'Mixed vegetables', 'quantity' => 0.15, 'unit' => 'kg', 'cost' => 20],
                    ['name' => 'Spices', 'quantity' => 1, 'unit' => 'pcs', 'cost' => 5],
                ],
            ],
            'Fish Curry' => [
                'summary' => 'Bengali-style fish curry.',
                'other_costs' => 12,
                'ingredients' => [
                    ['name' => 'Fish', 'quantity' => 0.16, 'unit' => 'kg', 'cost' => 60],
                    ['name' => 'Mustard oil', 'quantity' => 0.02, 'unit' => 'L', 'cost' => 10],
                    ['name' => 'Spices', 'quantity' => 1, 'unit' => 'pcs', 'cost' => 8],
                ],
            ],
            'Raita' => [
                'summary' => 'Yogurt raita.',
                'other_costs' => 4,
                'ingredients' => [
                    ['name' => 'Yogurt', 'quantity' => 0.1, 'unit' => 'kg', 'cost' => 12],
                    ['name' => 'Cucumber', 'quantity' => 0.03, 'unit' => 'kg', 'cost' => 3],
                ],
            ],
        ];

        $meals = [];
        foreach ($library as $name => $config) {
            $meal = MealItem::create([
                'name' => $name,
                'summary' => $config['summary'],
                'other_costs' => $config['other_costs'],
                'recipe_ingredient_cost' => 0,
                'total_cost' => $config['other_costs'],
                'note' => 'Seeded test meal item',
            ]);

            $recipe = Recipe::create([
                'meal_item_id' => $meal->id,
                'title' => "Standard {$name} Recipe",
                'instructions' => "Prepare {$name} using the listed ingredients. Follow standard kitchen SOP.",
                'training_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'is_active' => true,
            ]);

            foreach ($config['ingredients'] as $index => $ing) {
                RecipeIngredient::create([
                    'recipe_id' => $recipe->id,
                    'name' => $ing['name'],
                    'quantity' => $ing['quantity'],
                    'unit' => $ing['unit'],
                    'cost' => $ing['cost'],
                    'sort_order' => $index + 1,
                ]);
            }

            $meal->recalculateCosts();
            $meals[$name] = $meal;
        }

        $attachments = [
            'Vegetable Khichdi Thali' => ['Rice', 'Daal', 'Potato Mash', 'Salad', 'Mixed Veg'],
            'Traditional Vegetarian Thali' => ['Rice', 'Daal', 'Potato Mash', 'Salad', 'Mixed Veg', 'Raita'],
            'Royal Indian Thali' => ['Rice', 'Daal', 'Potato Mash', 'Salad', 'Beef Curry', 'Mixed Veg', 'Raita'],
            'Chicken Curry Thali' => ['Rice', 'Daal', 'Potato Mash', 'Salad', 'Chicken Curry'],
            'Bengali Fish Thali' => ['Rice', 'Daal', 'Potato Mash', 'Salad', 'Fish Curry'],
        ];

        foreach ($menus as $menu) {
            $names = $attachments[$menu->name] ?? ['Rice', 'Daal', 'Salad'];
            $sync = [];
            foreach ($names as $index => $mealName) {
                if (! isset($meals[$mealName])) {
                    continue;
                }
                $sync[$meals[$mealName]->id] = ['sort_order' => $index + 1];
            }
            $menu->mealItems()->sync($sync);
            $menu->update([
                'other_cost' => 20,
                'note' => 'Seeded menu packaging / misc costs',
            ]);
            $menu->recalculateMealsCost();
        }

        $this->command?->info('Seeded '.count($meals).' meal items with recipes/ingredients and attached them to '.$menus->count().' menus.');
    }
}
