<?php

namespace App\Models;

use App\Support\OrderCutoff;
use App\Support\OrderPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'menu_item_id',
        'package_subscription_id',
        'quantity',
        'delivery_date',
        'delivery_time',
        'total_amount',
        'food_amount',
        'charges_amount',
        'discount_amount',
        'kitchen_share_amount',
        'delivery_share_amount',
        'direct_cost_amount',
        'middo_rest_amount',
        'amount_paid',
        'prepaid_amount',
        'cash_collected',
        'cash_due_to_middo',
        'address',
        'receiver_name',
        'receiver_mobile',
        'area_id',
        'order_status',
        'payment_status',
        'payment_method',
        'coupon_id',
        'dispatched_at',
        'delivery_rider_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'dispatched_at' => 'datetime',
        'quantity' => 'integer',
        'total_amount' => 'integer',
        'food_amount' => 'integer',
        'charges_amount' => 'integer',
        'discount_amount' => 'integer',
        'kitchen_share_amount' => 'integer',
        'delivery_share_amount' => 'integer',
        'direct_cost_amount' => 'integer',
        'middo_rest_amount' => 'integer',
        'amount_paid' => 'integer',
        'prepaid_amount' => 'integer',
        'cash_collected' => 'integer',
        'cash_due_to_middo' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function appliedCharges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    public function moneyEvents(): HasMany
    {
        return $this->hasMany(OrderMoneyEvent::class);
    }

    public function partnerPayables(): HasMany
    {
        return $this->hasMany(PartnerPayable::class);
    }

    public function packageSubscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class);
    }

    public function isPackageOrder(): bool
    {
        return $this->package_subscription_id !== null;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function middoBoxLogs(): HasMany
    {
        return $this->hasMany(MiddoBoxLog::class);
    }

    public function orderMiddoBoxes(): HasMany
    {
        return $this->hasMany(OrderMiddoBox::class);
    }

    public function middoBoxes(): BelongsToMany
    {
        return $this->belongsToMany(MiddoBox::class, 'order_middo_boxes')
            ->withTimestamps();
    }

    public function deliveryRider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_rider_id');
    }

    public function isKitchenDispatched(): bool
    {
        return $this->dispatched_at !== null;
    }

    public function isAwaitingRiderAccept(): bool
    {
        return $this->isKitchenDispatched()
            && $this->delivery_rider_id === null
            && $this->order_status === 'packed';
    }

    public function isPacked(): bool
    {
        return $this->order_status === 'packed';
    }

    public function isOnTheWayToDelivery(): bool
    {
        return $this->order_status === 'on_the_way_to_delivery';
    }

    public function isAssignedToRider(?int $riderId = null): bool
    {
        if ($this->delivery_rider_id === null) {
            return false;
        }

        return $riderId === null || (int) $this->delivery_rider_id === (int) $riderId;
    }

    public function isDelivered(): bool
    {
        return in_array($this->order_status, ['delivered', 'delivered_and_paid'], true);
    }

    public function isPaid(): bool
    {
        return $this->order_status === 'delivered_and_paid'
            || $this->payment_status === 'paid';
    }

    public function amountPaidValue(): int
    {
        return (int) ($this->amount_paid ?? 0);
    }

    public function prepaidAmountValue(): int
    {
        return (int) ($this->prepaid_amount ?? 0);
    }

    /**
     * Customer bill after coupon/discount (food + charges − discount).
     * total_amount stores pre-discount line total; discount_amount is separate.
     */
    public function netTotalAmount(): int
    {
        return max(0, (int) $this->total_amount - (int) ($this->discount_amount ?? 0));
    }

    /**
     * Residual the customer still owes. Always respects discount_amount.
     */
    public function amountDue(): int
    {
        return max(0, $this->netTotalAmount() - $this->amountPaidValue());
    }

    public function paymentMethodKey(): ?string
    {
        return OrderPaymentMethod::resolve($this);
    }

    public function paymentMethodLabel(): string
    {
        return OrderPaymentMethod::labelForOrder($this);
    }

    /**
     * Cash the rider collected at delivery (for handovers).
     * Prefers explicit cash_collected. Legacy fallback only for COD rows that
     * predate the column — never for balance/gateway residuals.
     */
    public function cashCollectedAmount(): int
    {
        $collected = (int) ($this->cash_collected ?? 0);

        if ($collected > 0) {
            return $collected;
        }

        $method = $this->payment_method;
        if (in_array($method, [OrderPaymentMethod::BALANCE, OrderPaymentMethod::GATEWAY], true)) {
            return 0;
        }

        // Legacy COD paid deliveries (null/COD method, no prepaid, no cash_collected column).
        if (
            ($method === null || $method === '' || $method === OrderPaymentMethod::CASH_ON_DELIVERY)
            && $this->isPaid()
            && $this->prepaidAmountValue() === 0
            && $this->order_status === 'delivered_and_paid'
        ) {
            return $this->netTotalAmount();
        }

        return 0;
    }

    /**
     * Cash still owed to Middo after in-kind commission settle (Collection − Commission).
     * Null cash_due_to_middo = pre-R3 legacy → full cash collected.
     */
    public function dueToMiddoAmount(): int
    {
        if ($this->cashHandoverOrder()->exists()) {
            return 0;
        }

        if ($this->cash_due_to_middo !== null) {
            return max(0, (int) $this->cash_due_to_middo);
        }

        return $this->cashCollectedAmount();
    }

    public function commissionRetainedFromCashAmount(): int
    {
        if ($this->cash_due_to_middo === null) {
            return 0;
        }

        return max(0, $this->cashCollectedAmount() - (int) $this->cash_due_to_middo);
    }

    public function accountHolderName(): string
    {
        $this->loadMissing('user');

        $name = trim(($this->user?->first_name ?? '').' '.($this->user?->last_name ?? ''));

        return $name !== '' ? $name : 'Account holder';
    }

    public function accountHolderMobile(): ?string
    {
        $this->loadMissing('user');

        return $this->user?->mobile;
    }

    public function receiverDisplayName(): string
    {
        $receiver = trim((string) ($this->receiver_name ?? ''));

        return $receiver !== '' ? $receiver : $this->accountHolderName();
    }

    public function receiverDisplayMobile(): ?string
    {
        $mobile = trim((string) ($this->receiver_mobile ?? ''));

        return $mobile !== '' ? $mobile : $this->accountHolderMobile();
    }

    /**
     * True when checkout receiver is a different person than the account holder.
     */
    public function hasSeparateReceiver(): bool
    {
        $receiverName = trim((string) ($this->receiver_name ?? ''));
        $receiverMobile = trim((string) ($this->receiver_mobile ?? ''));

        if ($receiverName === '' && $receiverMobile === '') {
            return false;
        }

        $holderName = mb_strtolower(trim($this->accountHolderName()));
        $holderMobile = preg_replace('/\D+/', '', (string) $this->accountHolderMobile()) ?? '';
        $recvName = mb_strtolower($receiverName);
        $recvMobile = preg_replace('/\D+/', '', $receiverMobile) ?? '';

        $nameDiffers = $receiverName !== '' && $recvName !== $holderName;
        $mobileDiffers = $receiverMobile !== '' && $recvMobile !== '' && $recvMobile !== $holderMobile;

        return $nameDiffers || $mobileDiffers;
    }

    /**
     * @return array<string, mixed>
     */
    public function partyPayload(): array
    {
        $separate = $this->hasSeparateReceiver();

        return [
            'account_holder_name' => $this->accountHolderName(),
            'account_holder_mobile' => $this->accountHolderMobile(),
            'receiver_name' => $this->receiverDisplayName(),
            'receiver_mobile' => $this->receiverDisplayMobile(),
            'has_separate_receiver' => $separate,
            // Back-compat label used by existing blades
            'customer_name' => $separate
                ? $this->receiverDisplayName().' (acct: '.$this->accountHolderName().')'
                : $this->accountHolderName(),
            'amount_paid' => $this->amountPaidValue(),
            'amount_due' => $this->amountDue(),
            'prepaid_amount' => $this->prepaidAmountValue(),
            'cash_collected' => $this->cashCollectedAmount(),
            'payment_method' => $this->paymentMethodKey(),
            'payment_method_label' => $this->paymentMethodLabel(),
        ];
    }

    public function scopeKitchenDispatched($query)
    {
        return $query
            ->whereNotNull('dispatched_at')
            ->whereIn('order_status', ['packed', 'on_the_way_to_delivery']);
    }

    public function scopeDeliveredForRider($query, int $riderId)
    {
        return $query
            ->where('delivery_rider_id', $riderId)
            ->whereIn('order_status', ['delivered', 'delivered_and_paid']);
    }

    public const ACTIVE_STATUSES = [
        'pending',
        'processing',
        'ready',
        'packed',
        'on_the_way_to_delivery',
    ];

    public function scopeActive($query)
    {
        return $query->whereIn('order_status', self::ACTIVE_STATUSES);
    }

    public function isEditableByCorporate(): bool
    {
        return OrderCutoff::allowsModification($this);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OrderLog::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(OrderComplaint::class);
    }

    public function cashHandoverOrder(): HasOne
    {
        return $this->hasOne(CashHandoverOrder::class);
    }

    public function orderGroupOrder(): HasOne
    {
        return $this->hasOne(OrderGroupOrder::class);
    }

    public function orderGroup(): HasOneThrough
    {
        return $this->hasOneThrough(
            OrderGroup::class,
            OrderGroupOrder::class,
            'order_id',
            'id',
            'id',
            'order_group_id'
        );
    }

    public function scopeFuture($query)
    {
        return $query
            ->where('delivery_date', '>=', now('Asia/Dhaka')->toDateString())
            ->where('order_status', '!=', 'cancelled');
    }

    public function scopePast($query)
    {
        return $query->where('delivery_date', '<', now('Asia/Dhaka')->toDateString());
    }
}
