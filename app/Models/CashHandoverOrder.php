<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashHandoverOrder extends Model
{
    protected $fillable = [
        'cash_handover_id',
        'order_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(CashHandover::class, 'cash_handover_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
