<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenWarehouseHandoff extends Model
{
    /** Kitchen marked empty box ready to ship; awaiting area rider claim. */
    public const STATUS_RUN_REQUESTED = 'run_requested';

    /** Rider claimed the run; awaiting kitchen dispatch. */
    public const STATUS_RUN_CLAIMED = 'run_claimed';

    /** Kitchen dispatched to the claiming rider; awaiting rider box accept. */
    public const STATUS_DISPATCHED = 'dispatched';

    /** Rider accepted the box and is en route to Middo warehouse. */
    public const STATUS_IN_TRANSIT = 'in_transit';

    /** Rider handed box to ops; awaiting ops receive. */
    public const STATUS_HANDED_TO_OPS = 'handed_to_ops';

    /** Ops received — run complete. */
    public const STATUS_RECEIVED = 'received';

    /** @deprecated Mapped to STATUS_DISPATCHED */
    public const STATUS_READY_FOR_PICKUP = 'dispatched';

    /** @deprecated Mapped to STATUS_IN_TRANSIT */
    public const STATUS_RIDER_ACCEPTED = 'in_transit';

    /** @deprecated Mapped to STATUS_HANDED_TO_OPS */
    public const STATUS_DELIVERED = 'handed_to_ops';

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

    /**
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return [
            self::STATUS_RUN_REQUESTED,
            self::STATUS_RUN_CLAIMED,
            self::STATUS_DISPATCHED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_HANDED_TO_OPS,
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::openStatuses());
    }

    public function scopeRunRequested(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUN_REQUESTED)->whereNull('rider_id');
    }

    public function scopeReadyForPickup(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DISPATCHED);
    }

    public function isRunRequested(): bool
    {
        return $this->status === self::STATUS_RUN_REQUESTED;
    }

    public function isRunClaimed(): bool
    {
        return $this->status === self::STATUS_RUN_CLAIMED;
    }

    public function isDispatched(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function isHandedToOps(): bool
    {
        return $this->status === self::STATUS_HANDED_TO_OPS;
    }

    public function isReadyForPickup(): bool
    {
        return $this->isDispatched();
    }
}
