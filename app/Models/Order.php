<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'order_status',
        'payment_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'quantity' => 'integer',
        'total_amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function logs(): HasMany
    {
        return $this->hasMany(OrderLog::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(OrderComplaint::class);
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

    public function scopeActive($query)
    {
        return $query->whereIn('order_status', ['pending', 'processing']);
    }
}
