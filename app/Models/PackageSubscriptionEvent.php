<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageSubscriptionEvent extends Model
{
    public const TYPE_SCHEDULE_ASSIGNED = 'schedule_assigned';

    public const TYPE_MENU_SWAPPED = 'menu_swapped';

    public const TYPE_DAY_CANCELLED = 'day_cancelled';

    public const TYPE_DAY_UNCONFIRMED = 'day_unconfirmed';

    public const TYPE_DAY_REACTIVATED = 'day_reactivated';

    public const TYPE_DELIVERY_UPDATED = 'delivery_updated';

    public const TYPE_REMAINING_CANCELLED = 'remaining_cancelled';

    public const TYPE_FORCE_COMPLETED = 'force_completed';

    protected $fillable = [
        'package_subscription_id',
        'type',
        'summary',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class, 'package_subscription_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
