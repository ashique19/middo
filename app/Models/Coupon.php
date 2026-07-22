<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const APPLIES_ORDERS = 'orders';

    public const APPLIES_PACKAGES = 'packages';

    public const APPLIES_BOTH = 'both';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_subtotal',
        'max_discount',
        'usage_limit',
        'per_user_limit',
        'applies_to',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'integer',
        'min_subtotal' => 'integer',
        'max_discount' => 'integer',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appliesToOrders(): bool
    {
        return in_array($this->applies_to, [self::APPLIES_ORDERS, self::APPLIES_BOTH], true);
    }

    public function appliesToPackages(): bool
    {
        return in_array($this->applies_to, [self::APPLIES_PACKAGES, self::APPLIES_BOTH], true);
    }

    public function redemptionCount(): int
    {
        return $this->redemptions()->count();
    }
}
