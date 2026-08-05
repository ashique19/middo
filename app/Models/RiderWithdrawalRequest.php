<?php

namespace App\Models;

use App\Support\PayoutChannel;
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
        'payout_channel',
        'payout_details',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'attachment_path',
        'rider_ledger_entry_id',
        'middo_cash_ledger_entry_id',
        'middo_bank_account_id',
        'middo_bank_ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
            'payout_details' => 'array',
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

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(MiddoBankAccount::class, 'middo_bank_account_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function payoutChannelLabel(): string
    {
        return PayoutChannel::label((string) ($this->payout_channel ?: PayoutChannel::CASH));
    }

    public function payoutDetailsSummary(): string
    {
        return PayoutChannel::detailsSummary(
            (string) ($this->payout_channel ?: PayoutChannel::CASH),
            $this->payout_details
        );
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? asset($this->attachment_path) : null;
    }
}
