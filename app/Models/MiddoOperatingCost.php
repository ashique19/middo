<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MiddoOperatingCost extends Model
{
    public const TYPE_RIDER_COMMISSION = 'rider_commission';

    protected $fillable = [
        'cost_type',
        'amount',
        'run_type',
        'rider_user_id',
        'order_id',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
