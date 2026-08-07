<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenBoxRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'kitchen_id',
        'quantity',
        'status',
        'note',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reviewed_at' => 'datetime',
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

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public static function pendingQuantityForKitchen(int $kitchenId): int
    {
        return (int) self::query()
            ->pending()
            ->where('kitchen_id', $kitchenId)
            ->sum('quantity');
    }

    /**
     * Apply a warehouse shipment against the kitchen's oldest pending requests (FIFO).
     * Call inside a DB transaction with row locks when possible.
     *
     * @throws \RuntimeException when the kitchen has no pending request or qty exceeds remaining
     */
    public static function consumePendingForKitchen(int $kitchenId, int $shippedQty, ?int $reviewedBy = null): void
    {
        if ($shippedQty < 1) {
            throw new \InvalidArgumentException('Shipped quantity must be at least 1.');
        }

        $remaining = $shippedQty;

        $requests = self::query()
            ->pending()
            ->where('kitchen_id', $kitchenId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (int) $requests->sum('quantity');
        if ($available < 1) {
            throw new \RuntimeException('This kitchen has no pending box request. Ask them to Request box first.');
        }

        if ($shippedQty > $available) {
            throw new \RuntimeException(sprintf(
                'Cannot send %d boxes — kitchen only requested %d more.',
                $shippedQty,
                $available
            ));
        }

        foreach ($requests as $request) {
            if ($remaining < 1) {
                break;
            }

            $take = min($remaining, (int) $request->quantity);
            $left = (int) $request->quantity - $take;

            if ($left < 1) {
                $request->update([
                    'quantity' => 0,
                    'status' => self::STATUS_FULFILLED,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => now(),
                ]);
            } else {
                $request->update([
                    'quantity' => $left,
                ]);
            }

            $remaining -= $take;
        }
    }
}
