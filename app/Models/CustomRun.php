<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'from_label',
        'to_label',
        'area_id',
        'rider_user_id',
        'commission_amount',
        'status',
        'notes',
        'created_by',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_amount' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isStarted(): bool
    {
        return $this->status === self::STATUS_STARTED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function label(): string
    {
        return $this->from_label.' → '.$this->to_label;
    }

    /**
     * Visible to a rider: only runs ops assigned to them.
     */
    public function scopeVisibleToRider(Builder $query, User $rider): Builder
    {
        return $query->where('rider_user_id', (int) $rider->id);
    }
}
