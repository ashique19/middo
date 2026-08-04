<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderWithdrawalRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'rider_user_id',
        'amount',
        'status',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rider_ledger_entry_id',
        'middo_cash_ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
