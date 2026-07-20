<?php

namespace Tests\Feature\Ops;

use App\Livewire\Shared\RecipeManagerModal;
use App\Models\MealItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeManagerEditTest extends TestCase
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

    public function test_admin_can_edit_recipe_from_manager_modal_on_meal_item_show(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310444001']);

        $meal = MealItem::create([
            'name' => 'Rice',
            'summary' => 'Steamed basmati rice.',
            'total_cost' => 24,
        ]);

        $recipe = Recipe::create([
            'meal_item_id' => $meal->id,
            'title' => 'Standard Rice Recipe',
            'instructions' => 'Steam until fluffy.',
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'name' => 'Basmati rice',
            'quantity' => 100,
            'unit' => 'g',
            'cost' => 19,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($admin)
            ->test(RecipeManagerModal::class)
            ->dispatch('open-recipe-manager', mealItemId: $meal->id)
            ->assertSet('showModal', true)
            ->assertSet('mealItemId', $meal->id)
            ->call('startEdit', $recipe->id)
            ->assertSet('editing', true)
            ->assertSet('recipeId', $recipe->id)
            ->assertSet('title', 'Standard Rice Recipe')
            ->assertSet('ingredients.0.name', 'Basmati rice')
            ->assertSee('Edit Recipe');
    }
}
