<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenSettlementBatchItem extends Model
{
    protected $fillable = [
        'kitchen_settlement_batch_id',
        'partner_payable_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(KitchenSettlementBatch::class, 'kitchen_settlement_batch_id');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(PartnerPayable::class, 'partner_payable_id');
    }
}
