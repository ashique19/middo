<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderGroup extends Model
{
    protected $fillable = [
        'name',
        'menu_id',
        'area_id',
        'delivery_date',
        'kitchen_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kitchen_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function orderGroupOrders(): HasMany
    {
        return $this->hasMany(OrderGroupOrder::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_group_orders')
            ->withTimestamps();
    }

    public function kitchenDisplayName(): string
    {
        if (! $this->kitchen_id || ! $this->kitchen) {
            return 'Unassigned';
        }

        $name = trim($this->kitchen->name);

        return $name !== '' ? $name : 'Unassigned';
    }
}
