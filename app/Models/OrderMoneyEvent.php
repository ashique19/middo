<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderMoneyEvent extends Model
{
    public const TYPE_PLACED = 'placed';

    public const TYPE_CHARGE = 'charge';

    public const TYPE_DISCOUNT = 'discount';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_CASH_COLLECTED = 'cash_collected';

    public const TYPE_CASH_TO_MIDDO = 'cash_to_middo';

    public const TYPE_REFUND = 'refund';

    public const TYPE_KITCHEN_SHARE = 'kitchen_share';

    public const TYPE_DELIVERY_SHARE = 'delivery_share';

    public const TYPE_DIRECT_COST = 'direct_cost';

    public const TYPE_MIDDO_REST = 'middo_rest';

    public const TYPE_PAYABLE_SETTLED = 'payable_settled';

    public const BUCKET_REVENUE = 'revenue';

    public const BUCKET_CHARGE = 'charge';

    public const BUCKET_DISCOUNT = 'discount';

    public const BUCKET_CUSTOMER_PAYMENT = 'customer_payment';

    public const BUCKET_REFUND = 'refund';

    public const BUCKET_KITCHEN_PAYABLE = 'kitchen_payable';

    public const BUCKET_DELIVERY_PAYABLE = 'delivery_payable';

    public const BUCKET_DIRECT_COST = 'direct_cost';

    public const BUCKET_MIDDO_RETAINED = 'middo_retained';

    public const BUCKET_MIDDO_CASH = 'middo_cash';

    protected $fillable = [
        'order_id',
        'event_type',
        'bucket',
        'amount',
        'middo_cash_balance_after',
        'channel',
        'reference_type',
        'reference_id',
        'meta',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'middo_cash_balance_after' => 'integer',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
