<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPackageDay extends Model
{
    protected $fillable = [
        'meal_package_id',
        'delivery_date',
        'menu_item_id',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(MealPackage::class, 'meal_package_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
