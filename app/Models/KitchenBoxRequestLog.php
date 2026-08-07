<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenBoxRequestLog extends Model
{
    public const EVENT_REQUESTED = 'requested';

    public const EVENT_STAGED_FOR_PICKUP = 'staged_for_pickup';

    public const EVENT_RIDER_ACCEPTED = 'rider_accepted';

    public const EVENT_HANDED_TO_KITCHEN = 'handed_to_kitchen';

    public const EVENT_RECEIVED_AT_KITCHEN = 'received_at_kitchen';

    public const EVENT_CLOSED = 'closed';

    public const EVENT_CANCELLED = 'cancelled';

    protected $fillable = [
        'kitchen_box_request_id',
        'event',
        'note',
        'meta',
        'performed_by',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(KitchenBoxRequest::class, 'kitchen_box_request_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
