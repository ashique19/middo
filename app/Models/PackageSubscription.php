<?php

namespace App\Models;

use App\Support\PackageBilling;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const SCHEDULE_AWAITING = 'awaiting_schedule';

    public const SCHEDULE_SCHEDULED = 'scheduled';

    protected $fillable = [
        'user_id',
        'meal_package_id',
        'quantity',
        'start_date',
        'end_date',
        'target_month',
        'omitted_weekdays',
        'billable_days',
        'price_per_day',
        'total_amount',
        'amount_paid',
        'payment_status',
        'coupon_id',
        'discount_amount',
        'status',
        'schedule_status',
        'delivery_time',
        'address',
        'receiver_name',
        'receiver_mobile',
        'area_id',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'billable_days' => 'integer',
        'price_per_day' => 'integer',
        'total_amount' => 'integer',
        'amount_paid' => 'integer',
        'discount_amount' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'omitted_weekdays' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MealPackage::class, 'meal_package_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function selections(): HasMany
    {
        return $this->hasMany(PackageSubscriptionSelection::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->whereIn('order_status', Order::ACTIVE_STATUSES);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOccupyingMonth($query)
    {
        return $query
            ->where('status', '!=', self::STATUS_CANCELLED)
            ->whereNotNull('target_month')
            ->where('target_month', '!=', '');
    }

    /**
     * Target months (Y-m) the corporate already prepaid and cannot buy again.
     *
     * @return list<string>
     */
    public static function orderedMonthsForUser(int $userId): array
    {
        return static::query()
            ->forUser($userId)
            ->occupyingMonth()
            ->pluck('target_month')
            ->filter(fn ($month) => is_string($month) && $month !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function userHasPackageForMonth(int $userId, string $targetMonth): bool
    {
        $month = PackageBilling::normalizeTargetMonth($targetMonth);

        return static::query()
            ->forUser($userId)
            ->occupyingMonth()
            ->where('target_month', $month)
            ->exists();
    }

    public function isAwaitingSchedule(): bool
    {
        return $this->schedule_status === self::SCHEDULE_AWAITING;
    }

    public function isScheduled(): bool
    {
        return $this->schedule_status === self::SCHEDULE_SCHEDULED;
    }
}
