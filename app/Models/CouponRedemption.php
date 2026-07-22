<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    public const CONTEXT_ORDER = 'order';

    public const CONTEXT_PACKAGE = 'package';

    protected $fillable = [
        'coupon_id',
        'user_id',
        'code',
        'context',
        'original_amount',
        'discount_amount',
        'final_amount',
        'order_id',
        'package_subscription_id',
        'metadata',
    ];

    protected $casts = [
        'original_amount' => 'integer',
        'discount_amount' => 'integer',
        'final_amount' => 'integer',
        'metadata' => 'array',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function packageSubscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class);
    }
}
