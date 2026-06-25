<?php

namespace App\Models;

use App\Models\MiddoBox;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiddoBoxLog extends Model
{
    protected $fillable = [
        'order_id',
        'middo_box_id',
        'custody_status',
        'log_action',
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
