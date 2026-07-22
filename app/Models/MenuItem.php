<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'name',
        'summary',
        'price',
        'kitchen_commission',
        'delivery_commission',
        'thumbnail',
        'is_featured',
        'is_homepage',
        'display_order',
        'meals_cost',
        'other_cost',
        'note',
    ];

    protected $casts = [
        'price' => 'integer',
        'kitchen_commission' => 'integer',
        'delivery_commission' => 'integer',
        'meals_cost' => 'integer',
        'other_cost' => 'integer',
        'is_featured' => 'boolean',
        'is_homepage' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function mealItems(): BelongsToMany
    {
        return $this->belongsToMany(MealItem::class, 'menu_item_meal_item')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function recalculateMealsCost(): void
    {
        $mealsCost = (int) $this->mealItems()->sum('meal_items.total_cost');

        $this->update(['meals_cost' => $mealsCost]);
    }
}
