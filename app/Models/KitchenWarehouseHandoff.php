<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenWarehouseHandoff extends Model
{
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';

    public const STATUS_RIDER_ACCEPTED = 'rider_accepted';

    public const STATUS_DELIVERED = 'delivered';

    protected $fillable = [
        'middo_box_id',
        'kitchen_id',
        'rider_id',
        'status',
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(MiddoBox::class, 'middo_box_id');
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kitchen_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_READY_FOR_PICKUP,
            self::STATUS_RIDER_ACCEPTED,
        ]);
    }

    public function scopeReadyForPickup(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY_FOR_PICKUP);
    }

    public function isReadyForPickup(): bool
    {
        return $this->status === self::STATUS_READY_FOR_PICKUP;
    }
}
