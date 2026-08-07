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

    public const TYPE_CUSTOM_RUN = 'custom_run';

    /** Ops assigned warehouse boxes to a rider for kitchen delivery. */
    public const TYPE_OPS_TO_KITCHEN_BOX = 'ops_to_kitchen_box';

    /** Corporate marked empty Middo box ready for rider pickup. */
    public const TYPE_EMPTY_BOX_PICKUP = 'empty_box_pickup';

    /** Kitchen assigned empty box to rider for Middo warehouse return. */
    public const TYPE_KITCHEN_TO_OPS_BOX = 'kitchen_to_ops_box';

    /** Kitchen requested more Middo boxes from ops. */
    public const TYPE_KITCHEN_BOX_REQUEST = 'kitchen_box_request';

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
