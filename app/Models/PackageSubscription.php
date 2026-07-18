<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'meal_package_id',
        'quantity',
        'start_date',
        'end_date',
        'omitted_weekdays',
        'billable_days',
        'price_per_day',
        'total_amount',
        'amount_paid',
        'payment_status',
        'status',
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

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
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
}
