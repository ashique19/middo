<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MiddoBankLedgerEntry extends Model
{
    public const TYPE_EPS_IN_NET = 'eps_in_net';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_TRANSFER = 'transfer';

    protected $table = 'middo_bank_ledger';

    protected $fillable = [
        'middo_bank_account_id',
        'amount',
        'balance_after',
        'entry_type',
        'sub_gateway',
        'gross_amount',
        'fee_amount',
        'gateway_token',
        'merchant_transaction_id',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'gross_amount' => 'integer',
            'fee_amount' => 'integer',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(MiddoBankAccount::class, 'middo_bank_account_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
