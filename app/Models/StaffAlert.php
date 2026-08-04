<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAlert extends Model
{
    public const TYPE_GROUP_ASSIGNED = 'group_assigned';

    public const TYPE_ACCEPT_WINDOW_CLOSING = 'accept_window_closing';

    public const TYPE_NEEDS_REASSIGNMENT = 'needs_reassignment';

    public const TYPE_LUNCH_DISPATCH = 'lunch_dispatch';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'order_group_id',
        'meta',
        'dedupe_key',
        'read_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderGroup(): BelongsTo
    {
        return $this->belongsTo(OrderGroup::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->update(['read_at' => now()]);
    }
}
