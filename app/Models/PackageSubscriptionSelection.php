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
    ];

    protected $casts = [
        'day_count' => 'integer',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class, 'package_subscription_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
