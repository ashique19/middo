<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMiddoBox extends Model
{
    protected $fillable = [
        'order_id',
        'middo_box_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function middoBox(): BelongsTo
    {
        return $this->belongsTo(MiddoBox::class);
    }
}
