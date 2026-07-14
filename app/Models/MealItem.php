<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MealItem extends Model
{
    protected $fillable = [
        'name',
        'summary',
        'thumbnail',
        'recipe_ingredient_cost',
        'other_costs',
        'total_cost',
        'note',
    ];

    protected $casts = [
        'recipe_ingredient_cost' => 'integer',
        'other_costs' => 'integer',
        'total_cost' => 'integer',
    ];

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_meal_item')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function activeRecipe(): HasOne
    {
        return $this->hasOne(Recipe::class)->where('is_active', true);
    }

    public function recalculateCosts(): void
    {
        $active = $this->recipes()->where('is_active', true)->first();

        $recipeIngredientCost = $active
            ? (int) $active->ingredients()->sum('cost')
            : 0;

        $otherCosts = (int) $this->other_costs;

        $this->update([
            'recipe_ingredient_cost' => $recipeIngredientCost,
            'total_cost' => $recipeIngredientCost + $otherCosts,
        ]);

        $this->menuItems()->get()->each(fn (MenuItem $menu) => $menu->recalculateMealsCost());
    }
}
