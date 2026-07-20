<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Recipe extends Model
{
    protected $fillable = [
        'meal_item_id',
        'title',
        'instructions',
        'training_video_url',
        'is_active',
    ];

    protected $casts = [
        'meal_item_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function mealItem(): BelongsTo
    {
        return $this->belongsTo(MealItem::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RecipePhoto::class)->orderBy('sort_order');
    }

    public function ingredientCost(): int
    {
        return (int) $this->ingredients()->sum('cost');
    }

    public function activate(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('meal_item_id', $this->meal_item_id)
                ->where('id', '!=', $this->id)
                ->update(['is_active' => false]);

            $this->update(['is_active' => true]);

            $this->mealItem?->recalculateCosts();
        });
    }
}
