<?php

namespace App\Models;

use App\Support\PackageBilling;
use App\Support\PackageRefund;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const SCHEDULE_AWAITING = 'awaiting_schedule';

    public const SCHEDULE_PARTIAL = 'partially_scheduled';

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
        'charges_amount',
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
        'charges_amount' => 'integer',
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

    public function appliedCharges(): HasMany
    {
        return $this->hasMany(PackageSubscriptionCharge::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PackageSubscriptionEvent::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->whereIn('order_status', Order::ACTIVE_STATUSES);
    }

    /**
     * Counts for the ops package summary strip.
     *
     * @return array{scheduled: int, cancelled: int, refunded_amount: int, unconfirmed: int}
     */
    public function daySummary(): array
    {
        $orders = $this->relationLoaded('orders')
            ? $this->orders
            : $this->orders()->get(['id', 'order_status', 'amount_paid', 'total_amount', 'discount_amount', 'package_subscription_id']);

        $scheduled = $orders->where('order_status', '!=', 'cancelled')->count();
        $cancelledOrders = $orders->where('order_status', 'cancelled');
        $cancelled = $cancelledOrders->count();
        $refunded = 0;
        foreach ($cancelledOrders as $order) {
            $refunded += PackageRefund::orderRefundAmount($order);
        }

        return [
            'scheduled' => $scheduled,
            'cancelled' => $cancelled,
            'refunded_amount' => $refunded,
            'unconfirmed' => max(0, $this->remainingBillableDays()),
        ];
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

    public function isPartiallyScheduled(): bool
    {
        return $this->schedule_status === self::SCHEDULE_PARTIAL;
    }

    public function isScheduled(): bool
    {
        return $this->schedule_status === self::SCHEDULE_SCHEDULED;
    }

    public function canReceiveScheduleAssignments(): bool
    {
        return in_array($this->schedule_status, [self::SCHEDULE_AWAITING, self::SCHEDULE_PARTIAL], true)
            && $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Remaining prepaid menu-day quotas after confirmed or cancelled orders.
     * Cancelled days still consume prepaid slots (refunded) until re-activated;
     * undo (delete) frees a slot back to the unconfirmed list.
     *
     * @return array<int, int> menu_item_id => remaining day count
     */
    public function remainingSelectionCounts(): array
    {
        $this->loadMissing('selections');

        $used = $this->orders()
            ->selectRaw('menu_item_id, COUNT(*) as used_count')
            ->groupBy('menu_item_id')
            ->pluck('used_count', 'menu_item_id');

        $remaining = [];
        foreach ($this->selections as $selection) {
            $menuId = (int) $selection->menu_item_id;
            $remaining[$menuId] = max(
                0,
                (int) $selection->day_count - (int) ($used[$menuId] ?? 0)
            );
        }

        return $remaining;
    }

    public function remainingBillableDays(): int
    {
        return (int) array_sum($this->remainingSelectionCounts());
    }
}
