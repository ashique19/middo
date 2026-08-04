<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiddoBoxLog extends Model
{
    protected $fillable = [
        'order_id',
        'middo_box_id',
        'custody_status',
        'log_action',
        'notes',
        'performed_by',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function middoBox(): BelongsTo
    {
        return $this->belongsTo(MiddoBox::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
