<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenMiddoTransfer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'kitchen_user_id',
        'amount',
        'status',
        'proof_path',
        'reference_code',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'middo_cash_ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kitchen_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function proofUrl(): ?string
    {
        return $this->proof_path ? asset($this->proof_path) : null;
    }
}
