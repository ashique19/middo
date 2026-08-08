<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenBoxRequestBox extends Model
{
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';

    public const STATUS_RIDER_ACCEPTED = 'rider_accepted';

    public const STATUS_HANDED_TO_KITCHEN = 'handed_to_kitchen';

    public const STATUS_RECEIVED = 'received';

    /** @return list<string> */
    public static function openHandoffStatuses(): array
    {
        return [
            self::STATUS_READY_FOR_PICKUP,
            self::STATUS_RIDER_ACCEPTED,
            self::STATUS_HANDED_TO_KITCHEN,
        ];
    }

    protected $fillable = [
        'kitchen_box_request_id',
        'middo_box_id',
        'rider_id',
        'status',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(KitchenBoxRequest::class, 'kitchen_box_request_id');
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(MiddoBox::class, 'middo_box_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function scopeOpenHandoff(Builder $query): Builder
    {
        return $query->whereIn('status', self::openHandoffStatuses());
    }

    public function isOpenHandoff(): bool
    {
        return in_array($this->status, self::openHandoffStatuses(), true);
    }
}
