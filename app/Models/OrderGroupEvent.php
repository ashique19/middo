<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderGroupEvent extends Model
{
    public const TYPE_ACCEPT = 'accept';

    public const TYPE_DECLINE = 'decline';

    public const TYPE_SHORTAGE = 'shortage';

    public const TYPE_RELEASE = 'release';

    protected $fillable = [
        'order_group_id',
        'kitchen_id',
        'type',
        'reason',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function orderGroup(): BelongsTo
    {
        return $this->belongsTo(OrderGroup::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kitchen_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
