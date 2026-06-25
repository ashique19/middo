<?php

namespace App\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'name',
        'summary',
        'price',
        'kitchen_commission',
        'thumbnail',
        'is_featured',
        'is_homepage',
        'display_order'
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
