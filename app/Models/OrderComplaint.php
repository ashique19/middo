<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderComplaint extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'order_id',
        'parent_id',
        'is_reply',
        'status',
        'category',
        'message',
        'attachment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_reply' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isOpen(): bool
    {
        return ($this->status ?? self::STATUS_OPEN) === self::STATUS_OPEN;
    }

    public function isResolved(): bool
    {
        return ($this->status ?? self::STATUS_OPEN) === self::STATUS_RESOLVED;
    }

    public function markResolved(?int $actorId = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'updated_by' => $actorId,
        ]);
    }

    public static function threadForOrder(int $orderId): ?self
    {
        return self::query()
            ->where('order_id', $orderId)
            ->whereNull('parent_id')
            ->latest('id')
            ->first();
    }

    public function threadMessages()
    {
        $rootId = $this->parent_id ?? $this->id;

        return self::query()
            ->where('order_id', $this->order_id)
            ->where(function ($query) use ($rootId) {
                $query->where('id', $rootId)->orWhere('parent_id', $rootId);
            })
            ->with(['createdBy:id,first_name,last_name'])
            ->orderBy('created_at')
            ->get();
    }
}
