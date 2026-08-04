<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashHandover extends Model
{
    public const TARGET_KITCHEN = 'kitchen';

    public const TARGET_MIDDO = 'middo';

    protected $fillable = [
        'rider_id',
        'amount',
        'target',
        'status',
        'accepted_by',
        'accepted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CashHandoverOrder::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'cash_handover_orders')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isKitchenTarget(): bool
    {
        return ($this->target ?: self::TARGET_KITCHEN) === self::TARGET_KITCHEN;
    }

    public function isMiddoTarget(): bool
    {
        return $this->target === self::TARGET_MIDDO;
    }
}
