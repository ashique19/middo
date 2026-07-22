<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCharge extends Model
{
    protected $fillable = [
        'order_id',
        'charge_id',
        'name',
        'category',
        'calculation',
        'unit_amount',
        'quantity',
        'amount',
    ];

    protected $casts = [
        'unit_amount' => 'integer',
        'quantity' => 'integer',
        'amount' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
