<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KitchenAccountLedgerEntry extends Model
{
    protected $table = 'kitchen_account_ledger';

    protected $fillable = [
        'kitchen_user_id',
        'amount',
        'balance_after',
        'entry_type',
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
        ];
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kitchen_user_id');
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
