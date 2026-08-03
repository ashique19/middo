<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageSubscriptionSelection extends Model
{
    protected $fillable = [
        'package_subscription_id',
        'menu_item_id',
        'day_count',
        'unit_price',
    ];

    protected $casts = [
        'day_count' => 'integer',
        'unit_price' => 'integer',
    ];

    public function lineTotal(int $quantity = 1): int
    {
        return max(1, $quantity) * (int) $this->unit_price * (int) $this->day_count;
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class, 'package_subscription_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
