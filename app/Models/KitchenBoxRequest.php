<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenBoxRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    /** @deprecated Use STATUS_CLOSED — kept for legacy rows until migrated */
    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'kitchen_id',
        'quantity',
        'allocated_qty',
        'status',
        'note',
        'closed_note',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'allocated_qty' => 'integer',
        'reviewed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kitchen_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function requestBoxes(): HasMany
    {
        return $this->hasMany(KitchenBoxRequestBox::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(KitchenBoxRequestLog::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->allocated_qty);
    }

    public static function pendingQuantityForKitchen(int $kitchenId): int
    {
        return (int) self::query()
            ->open()
            ->where('kitchen_id', $kitchenId)
            ->get()
            ->sum(fn (self $request) => $request->remainingQuantity());
    }

    public function canClose(): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        if ((int) $this->allocated_qty < 1) {
            return false;
        }

        return ! $this->requestBoxes()
            ->where('status', '!=', KitchenBoxRequestBox::STATUS_RECEIVED)
            ->exists();
    }
}
