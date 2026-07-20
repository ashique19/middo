<?php

namespace Tests\Feature\Ops;

use App\Models\MealItem;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuHierarchyShowTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name]);
    }

    private function user(string $roleName, array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => ucfirst($roleName),
            'last_name' => 'User',
            'mobile' => '01310'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'password' => '12345678',
            'role_id' => $this->role($roleName)->id,
            'status' => 'active',
            'is_mobile_verified' => true,
        ], $overrides));
    }

    public function test_admin_can_drill_menu_to_meal_to_recipe_ingredients(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310555001']);

        $menu = MenuItem::create([
            'name' => 'Lunch Box A',
            'price' => 350,
            'meals_cost' => 120,
            'other_cost' => 30,
            'kitchen_commission' => 40,
        ]);

        $meal = MealItem::create([
            'name' => 'Chicken Curry',
            'summary' => 'Spicy curry',
            'recipe_ingredient_cost' => 80,
            'other_costs' => 20,
            'total_cost' => 100,
        ]);
        $menu->mealItems()->attach($meal->id, ['sort_order' => 1]);

        $recipe = Recipe::create([
            'meal_item_id' => $meal->id,
            'title' => 'Classic Curry',
            'instructions' => 'Cook slowly.',
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'name' => 'Chicken',
            'quantity' => 200,
            'unit' => 'g',
            'cost' => 60,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.menu.index'))
            ->assertOk()
            ->assertSee('Lunch Box A')
            ->assertSee('Meal items →')
            ->assertSee(route('admin.menu.show', $menu), false);

        $this->actingAs($admin)
            ->get(route('admin.menu.show', $menu))
            ->assertOk()
            ->assertSee('Chicken Curry')
            ->assertSee('Recipes →')
            ->assertSee(route('admin.meal-items.show', $meal), false);

        $this->actingAs($admin)
            ->get(route('admin.meal-items.index'))
            ->assertOk()
            ->assertSee('Chicken Curry')
            ->assertSee('Recipes →')
            ->assertSee('Ingredients →')
            ->assertSee(route('admin.meal-items.show', $meal), false)
            ->assertSee(route('admin.recipes.show', $recipe), false);

        $this->actingAs($admin)
            ->get(route('admin.meal-items.show', $meal))
            ->assertOk()
            ->assertSee('Classic Curry')
            ->assertSee('Ingredients →')
            ->assertSee('Lunch Box A')
            ->assertSee(route('admin.recipes.show', $recipe), false);

        $this->actingAs($admin)
            ->get(route('admin.recipes.show', $recipe))
            ->assertOk()
            ->assertSee('Classic Curry')
            ->assertSee('Chicken')
            ->assertSee('200')
            ->assertSee('g')
            ->assertSee('Cook slowly.');
    }

    public function test_operation_can_view_hierarchy_read_only(): void
    {
        $ops = $this->user('operation', ['mobile' => '01310555002']);
        $meal = MealItem::create(['name' => 'Dal', 'total_cost' => 40]);
        $recipe = Recipe::create([
            'meal_item_id' => $meal->id,
            'title' => 'Simple Dal',
            'is_active' => true,
        ]);

        $this->actingAs($ops)
            ->get(route('operation.meal-items.show', $meal))
            ->assertOk()
            ->assertSee('Simple Dal')
            ->assertDontSee('Manage recipes');

        $this->actingAs($ops)
            ->get(route('operation.recipes.show', $recipe))
            ->assertOk()
            ->assertSee('Simple Dal')
            ->assertDontSee('Manage recipes');
    }
}
