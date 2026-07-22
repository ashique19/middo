<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageSubscriptionCharge extends Model
{
    protected $fillable = [
        'package_subscription_id',
        'charge_id',
        'menu_item_id',
        'name',
        'category',
        'calculation',
        'unit_amount',
        'quantity',
        'amount',
    ];

    protected $casts = [
        'unit_amount' => 'integer',
        'quantity' => 'integer',
        'amount' => 'integer',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class, 'package_subscription_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
