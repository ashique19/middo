<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageCheckoutIntent extends Model
{
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAID_AWAITING_OTP = 'paid_awaiting_otp';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'payment_token',
        'status',
        'meal_package_id',
        'quantity',
        'omitted_weekdays',
        'target_month',
        'selections',
        'amount',
        'customer_name',
        'mobile',
        'address_line1',
        'city_id',
        'area_id',
        'delivery_window',
        'paid_at',
        'otp_last_sent_at',
        'package_subscription_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount' => 'integer',
        'omitted_weekdays' => 'array',
        'selections' => 'array',
        'paid_at' => 'datetime',
        'otp_last_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MealPackage::class, 'meal_package_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class, 'package_subscription_id');
    }

    public function isPaidAwaitingOtp(): bool
    {
        return $this->status === self::STATUS_PAID_AWAITING_OTP;
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === self::STATUS_AWAITING_PAYMENT;
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePaidAwaitingOtp($query)
    {
        return $query->where('status', self::STATUS_PAID_AWAITING_OTP);
    }

    public function confirmUrl(): string
    {
        return route('corporates.packages.confirm', ['token' => $this->payment_token]);
    }
}
