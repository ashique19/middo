<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPayable extends Model
{
    public const ROLE_KITCHEN = 'kitchen';

    public const ROLE_DELIVERY = 'delivery';

    public const STATUS_OPEN = 'open';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'order_id',
        'beneficiary_user_id',
        'beneficiary_role',
        'amount',
        'status',
        'settled_at',
        'settled_by',
        'middo_cash_ledger_entry_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'settled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function settledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
