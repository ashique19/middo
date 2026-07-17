<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'menu_item_id',
        'quantity',
        'delivery_date',
        'delivery_time',
        'total_amount',
        'address',
        'area_id',
        'order_status',
        'payment_status',
        'dispatched_at',
        'delivery_rider_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'dispatched_at' => 'datetime',
        'quantity' => 'integer',
        'total_amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function middoBoxLogs(): HasMany
    {
        return $this->hasMany(MiddoBoxLog::class);
    }

    public function orderMiddoBoxes(): HasMany
    {
        return $this->hasMany(OrderMiddoBox::class);
    }

    public function middoBoxes(): BelongsToMany
    {
        return $this->belongsToMany(MiddoBox::class, 'order_middo_boxes')
            ->withTimestamps();
    }

    public function deliveryRider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_rider_id');
    }

    public function isKitchenDispatched(): bool
    {
        return $this->dispatched_at !== null;
    }

    public function isAwaitingRiderAccept(): bool
    {
        return $this->isKitchenDispatched()
            && $this->delivery_rider_id === null
            && $this->order_status === 'packed';
    }

    public function isPacked(): bool
    {
        return $this->order_status === 'packed';
    }

    public function isOnTheWayToDelivery(): bool
    {
        return $this->order_status === 'on_the_way_to_delivery';
    }

    public function isAssignedToRider(?int $riderId = null): bool
    {
        if ($this->delivery_rider_id === null) {
            return false;
        }

        return $riderId === null || (int) $this->delivery_rider_id === (int) $riderId;
    }

    public function isDelivered(): bool
    {
        return in_array($this->order_status, ['delivered', 'delivered_and_paid'], true);
    }

    public function isPaid(): bool
    {
        return $this->order_status === 'delivered_and_paid'
            || $this->payment_status === 'paid';
    }

    public function scopeKitchenDispatched($query)
    {
        return $query
            ->whereNotNull('dispatched_at')
            ->whereIn('order_status', ['packed', 'on_the_way_to_delivery']);
    }

    public function scopeDeliveredForRider($query, int $riderId)
    {
        return $query
            ->where('delivery_rider_id', $riderId)
            ->whereIn('order_status', ['delivered', 'delivered_and_paid']);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('order_status', [
            'pending',
            'processing',
            'packed',
            'on_the_way_to_delivery',
        ]);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OrderLog::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(OrderComplaint::class);
    }

    public function cashHandoverOrder(): HasOne
    {
        return $this->hasOne(CashHandoverOrder::class);
    }

    public function orderGroupOrder(): HasOne
    {
        return $this->hasOne(OrderGroupOrder::class);
    }

    public function orderGroup(): HasOneThrough
    {
        return $this->hasOneThrough(
            OrderGroup::class,
            OrderGroupOrder::class,
            'order_id',
            'id',
            'id',
            'order_group_id'
        );
    }

    public function scopeFuture($query)
    {
        return $query
            ->where('delivery_date', '>=', now('Asia/Dhaka')->toDateString())
            ->where('order_status', '!=', 'cancelled');
    }

    public function scopePast($query)
    {
        return $query->where('delivery_date', '<', now('Asia/Dhaka')->toDateString());
    }
}
