<?php

namespace App\Support;

use App\Models\CashHandover;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderMoneyEvent;
use App\Models\PartnerPayable;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderMoneyFlow
{
    /**
     * Snapshot billing + share decomposition onto the order and write the initial tree events.
     */
    public static function onOrderCreated(Order $order): void
    {
        if (! Schema::hasTable('order_money_events')) {
            return;
        }

        $order->loadMissing('menuItem');
        $breakdown = self::computeBreakdown($order);
        $order->forceFill($breakdown)->saveQuietly();

        self::write($order, OrderMoneyEvent::TYPE_PLACED, OrderMoneyEvent::BUCKET_REVENUE, (int) $breakdown['food_amount'], [
            'channel' => 'system',
            'description' => 'Food revenue (menu × qty, VAT-inclusive)',
            'meta' => [
                'quantity' => (int) $order->quantity,
                'unit_price' => self::unitFoodPrice($order),
                'vat_rate_pct' => (float) $breakdown['vat_rate_pct'],
                'vat_amount' => (int) $breakdown['vat_amount'],
                'food_ex_vat' => (int) $breakdown['food_amount'] - (int) $breakdown['vat_amount'],
            ],
        ]);

        if ((int) $breakdown['vat_amount'] > 0) {
            self::write($order, OrderMoneyEvent::TYPE_VAT, OrderMoneyEvent::BUCKET_VAT, (int) $breakdown['vat_amount'], [
                'channel' => 'system',
                'description' => sprintf(
                    'Food VAT %.2f%% unbundled (inclusive)',
                    (float) $breakdown['vat_rate_pct']
                ),
                'meta' => [
                    'scope' => 'food',
                    'vat_rate_pct' => (float) $breakdown['vat_rate_pct'],
                    'food_inclusive' => (int) $breakdown['food_amount'],
                    'food_ex_vat' => (int) $breakdown['food_amount'] - (int) $breakdown['vat_amount'],
                ],
            ]);
        }

        $deliveryNet = (int) ($breakdown['delivery_charge_amount'] ?? 0);
        $otherCharges = (int) ($breakdown['other_charges_amount'] ?? 0);
        $deliveryDiscount = (int) ($breakdown['delivery_discount_amount'] ?? 0);
        $deliveryVat = (int) ($breakdown['delivery_vat_amount'] ?? 0);

        if ($deliveryNet + $deliveryDiscount > 0) {
            self::write($order, OrderMoneyEvent::TYPE_CHARGE, OrderMoneyEvent::BUCKET_CHARGE, $deliveryNet + $deliveryDiscount, [
                'channel' => 'system',
                'description' => 'Delivery charge (gross, VAT-inclusive)',
                'meta' => [
                    'scope' => 'delivery',
                    'delivery_gross' => $deliveryNet + $deliveryDiscount,
                    'delivery_discount' => $deliveryDiscount,
                    'delivery_net' => $deliveryNet,
                    'delivery_vat_rate_pct' => (float) ($breakdown['delivery_vat_rate_pct'] ?? 0),
                    'delivery_vat_amount' => $deliveryVat,
                    'delivery_ex_vat' => max(0, $deliveryNet - $deliveryVat),
                ],
            ]);
        }

        if ($deliveryDiscount > 0) {
            self::write($order, OrderMoneyEvent::TYPE_DISCOUNT, OrderMoneyEvent::BUCKET_DISCOUNT, -1 * $deliveryDiscount, [
                'channel' => 'system',
                'description' => 'Delivery coupon',
                'meta' => ['scope' => 'delivery'],
            ]);
        }

        if ($deliveryVat > 0) {
            self::write($order, OrderMoneyEvent::TYPE_VAT, OrderMoneyEvent::BUCKET_VAT, $deliveryVat, [
                'channel' => 'system',
                'description' => sprintf(
                    'Delivery VAT %.2f%% unbundled from net delivery (inclusive)',
                    (float) ($breakdown['delivery_vat_rate_pct'] ?? 0)
                ),
                'meta' => [
                    'scope' => 'delivery',
                    'vat_rate_pct' => (float) ($breakdown['delivery_vat_rate_pct'] ?? 0),
                    'delivery_net_inclusive' => $deliveryNet,
                    'delivery_ex_vat' => max(0, $deliveryNet - $deliveryVat),
                ],
            ]);
        }

        if ($otherCharges > 0) {
            self::write($order, OrderMoneyEvent::TYPE_CHARGE, OrderMoneyEvent::BUCKET_CHARGE, $otherCharges, [
                'channel' => 'system',
                'description' => 'Other charges (handling/packaging/other — no delivery VAT)',
                'meta' => ['scope' => 'other_charges'],
            ]);
        }

        $nonDeliveryDiscount = max(0, (int) $breakdown['discount_amount'] - $deliveryDiscount);
        if ($nonDeliveryDiscount > 0) {
            self::write($order, OrderMoneyEvent::TYPE_DISCOUNT, OrderMoneyEvent::BUCKET_DISCOUNT, -1 * $nonDeliveryDiscount, [
                'channel' => 'system',
                'description' => 'Discount / coupon (non-delivery)',
                'meta' => ['scope' => 'other'],
            ]);
        }

        if ((int) $order->prepaid_amount > 0 || (int) $order->amount_paid > 0) {
            $paid = max((int) $order->prepaid_amount, (int) $order->amount_paid);
            self::write($order, OrderMoneyEvent::TYPE_PAYMENT, OrderMoneyEvent::BUCKET_CUSTOMER_PAYMENT, $paid, [
                'channel' => self::paymentChannel($order),
                'description' => 'Customer payment at checkout ('.self::paymentChannel($order).')',
            ]);
        }
    }

    /**
     * React to payment / cash / fulfillment status changes.
     */
    public static function onOrderUpdated(Order $order): void
    {
        if (! Schema::hasTable('order_money_events')) {
            return;
        }

        $changes = $order->getChanges();

        // Capture transitions before any nested saveQuietly() syncs originals.
        $newlyDispatched = array_key_exists('dispatched_at', $changes)
            && $order->dispatched_at !== null
            && blank($order->getOriginal('dispatched_at'));

        $becameDeliveredAndPaid = array_key_exists('order_status', $changes)
            && $order->order_status === 'delivered_and_paid'
            && $order->getOriginal('order_status') !== 'delivered_and_paid';

        $becameCancelled = array_key_exists('order_status', $changes)
            && $order->order_status === 'cancelled';

        if (array_key_exists('cash_collected', $changes)) {
            $from = (int) $order->getOriginal('cash_collected');
            $to = (int) $order->cash_collected;
            $delta = $to - $from;
            if ($delta > 0) {
                self::write($order, OrderMoneyEvent::TYPE_CASH_COLLECTED, OrderMoneyEvent::BUCKET_CUSTOMER_PAYMENT, $delta, [
                    'channel' => 'cash',
                    'description' => 'Cash collected by rider (held in rider float until handover)',
                    'meta' => ['rider_id' => $order->delivery_rider_id],
                ]);
            }
        }

        if (array_key_exists('amount_paid', $changes)) {
            $from = (int) $order->getOriginal('amount_paid');
            $to = (int) $order->amount_paid;
            $delta = $to - $from;
            if ($delta > 0) {
                // Avoid double-counting cash collections already logged.
                $cashDelta = 0;
                if (array_key_exists('cash_collected', $changes)) {
                    $cashDelta = max(0, (int) $order->cash_collected - (int) $order->getOriginal('cash_collected'));
                }
                $nonCash = $delta - $cashDelta;
                if ($nonCash > 0) {
                    self::write($order, OrderMoneyEvent::TYPE_PAYMENT, OrderMoneyEvent::BUCKET_CUSTOMER_PAYMENT, $nonCash, [
                        'channel' => self::paymentChannel($order),
                        'description' => 'Customer payment ('.self::paymentChannel($order).')',
                    ]);
                }
            } elseif ($delta < 0) {
                self::write($order, OrderMoneyEvent::TYPE_REFUND, OrderMoneyEvent::BUCKET_REFUND, $delta, [
                    'channel' => 'wallet',
                    'description' => 'Payment reversed / refunded',
                ]);
            }
        }

        if ($newlyDispatched) {
            self::accrueKitchenShareOnDispatch($order);
        }

        if ($becameDeliveredAndPaid) {
            self::accrueShares($order);
        }

        if ($becameCancelled) {
            self::voidOpenPayables($order);
        }
    }

    /**
     * Rider cash accepted by kitchen: kitchen holds the cash, so Middo's debt to kitchen
     * decreases (or kitchen begins to owe Middo if cash exceeds dispatched share).
     */
    public static function recordCashHandover(CashHandover $handover, ?int $kitchenUserId = null): void
    {
        if (! Schema::hasTable('order_money_events')) {
            return;
        }

        $handover->loadMissing('items.order.orderGroup');
        $kitchenBalance = $kitchenUserId ? KitchenAccountLedger::balance($kitchenUserId) : null;

        foreach ($handover->items as $item) {
            $order = $item->order;
            if (! $order) {
                continue;
            }
            $amount = (int) $item->amount;
            if ($amount < 1) {
                continue;
            }

            self::write($order, OrderMoneyEvent::TYPE_CASH_TO_KITCHEN, OrderMoneyEvent::BUCKET_KITCHEN_CASH, $amount, [
                'channel' => 'cash',
                'description' => "Cash handed to kitchen (handover #{$handover->id})",
                'reference' => $handover,
                'meta' => [
                    'handover_id' => $handover->id,
                    'rider_id' => $handover->rider_id,
                    'kitchen_id' => $kitchenUserId ?? $order->orderGroup?->kitchen_id,
                    'kitchen_balance_after' => $kitchenBalance,
                ],
            ]);
        }
    }

    /**
     * On dispatch Middo owes the kitchen their share — credit kitchen wallet.
     */
    public static function accrueKitchenShareOnDispatch(Order $order): void
    {
        if (! Schema::hasTable('order_money_events')) {
            return;
        }

        if (OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_KITCHEN_SHARE)
            ->exists()) {
            return;
        }

        $order->loadMissing(['menuItem', 'orderGroup']);
        $breakdown = self::computeBreakdown($order);
        $order->forceFill([
            'kitchen_share_amount' => $breakdown['kitchen_share_amount'],
            'food_amount' => $breakdown['food_amount'],
            'vat_rate_pct' => $breakdown['vat_rate_pct'],
            'vat_amount' => $breakdown['vat_amount'],
            'charges_amount' => $breakdown['charges_amount'],
            'discount_amount' => $breakdown['discount_amount'],
        ])->saveQuietly();

        $kitchenAmount = (int) $breakdown['kitchen_share_amount'];
        if ($kitchenAmount < 1) {
            return;
        }

        $kitchenId = $order->orderGroup?->kitchen_id;
        if (! $kitchenId) {
            return;
        }

        self::write($order, OrderMoneyEvent::TYPE_KITCHEN_SHARE, OrderMoneyEvent::BUCKET_KITCHEN_PAYABLE, -1 * $kitchenAmount, [
            'channel' => 'accrual',
            'description' => 'Kitchen share accrued on dispatch (Middo owes kitchen)',
            'meta' => ['kitchen_id' => $kitchenId],
        ]);

        self::upsertPayable(
            $order,
            PartnerPayable::ROLE_KITCHEN,
            $kitchenId,
            $kitchenAmount
        );

        $payable = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
            ->first();

        KitchenAccountLedger::credit(
            (int) $kitchenId,
            $kitchenAmount,
            'share_accrued',
            $payable ? PartnerPayable::class : null,
            $payable?->id,
            'Kitchen share for dispatched order #'.$order->id,
            $order->updated_by
        );
    }

    /**
     * On lunch run start (rider accept): Middo owes rider menu.delivery_commission × qty
     * (or rider lunch override). Credits rider wallet once.
     */
    public static function accrueDeliveryShareOnRunStart(Order $order, User $rider): void
    {
        if (! Schema::hasTable('order_money_events')) {
            return;
        }

        if (OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_DELIVERY_SHARE)
            ->exists()) {
            return;
        }

        $amount = RiderCommission::forLunchOrder($rider, $order);
        if ($amount < 1) {
            return;
        }

        $order->loadMissing(['menuItem', 'orderGroup']);
        $breakdown = self::computeBreakdown($order);
        // Prefer effective rider amount (may be override) over raw menu breakdown.
        $order->forceFill([
            'delivery_share_amount' => $amount,
            'food_amount' => $breakdown['food_amount'],
            'charges_amount' => $breakdown['charges_amount'],
            'discount_amount' => $breakdown['discount_amount'],
            'kitchen_share_amount' => $breakdown['kitchen_share_amount'],
        ])->saveQuietly();

        self::write($order, OrderMoneyEvent::TYPE_DELIVERY_SHARE, OrderMoneyEvent::BUCKET_DELIVERY_PAYABLE, -1 * $amount, [
            'channel' => 'accrual',
            'description' => 'Delivery share accrued on run start (Middo owes rider)',
            'meta' => [
                'rider_id' => $rider->id,
                'run_type' => DeliveryRunType::KITCHEN_TO_CORPORATE,
                'source' => 'menu_or_override',
            ],
        ]);

        self::upsertPayable(
            $order,
            PartnerPayable::ROLE_DELIVERY,
            $rider->id,
            $amount
        );

        $payable = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
            ->first();

        if (Schema::hasTable('rider_account_ledger')) {
            RiderAccountLedger::credit(
                (int) $rider->id,
                $amount,
                'commission_accrued',
                $payable ? PartnerPayable::class : Order::class,
                $payable?->id ?? $order->id,
                'Lunch run commission for order #'.$order->id,
                $rider->id
            );
        }
    }

    public static function accrueShares(Order $order): void
    {
        if (OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_MIDDO_REST)
            ->exists()) {
            return;
        }

        $order->loadMissing(['menuItem', 'orderGroup.kitchen', 'deliveryRider']);
        $breakdown = self::computeBreakdown($order);
        $order->forceFill($breakdown)->saveQuietly();

        // Kitchen share is accrued on dispatch (see accrueKitchenShareOnDispatch).
        // Delivery lunch share is accrued on rider pickup (see accrueDeliveryShareOnRunStart).

        $deliveryAlreadyAccrued = OrderMoneyEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', OrderMoneyEvent::TYPE_DELIVERY_SHARE)
            ->exists();

        if (! $deliveryAlreadyAccrued && (int) $breakdown['delivery_share_amount'] > 0) {
            self::write($order, OrderMoneyEvent::TYPE_DELIVERY_SHARE, OrderMoneyEvent::BUCKET_DELIVERY_PAYABLE, -1 * (int) $breakdown['delivery_share_amount'], [
                'channel' => 'accrual',
                'description' => 'Delivery share accrued (payable)',
                'meta' => ['rider_id' => $order->delivery_rider_id],
            ]);
            self::upsertPayable(
                $order,
                PartnerPayable::ROLE_DELIVERY,
                $order->delivery_rider_id,
                (int) $breakdown['delivery_share_amount']
            );
        }

        if ((int) $breakdown['direct_cost_amount'] > 0) {
            self::write($order, OrderMoneyEvent::TYPE_DIRECT_COST, OrderMoneyEvent::BUCKET_DIRECT_COST, -1 * (int) $breakdown['direct_cost_amount'], [
                'channel' => 'system',
                'description' => 'Estimated direct cost (meals + other menu costs)',
            ]);
        }

        self::write($order, OrderMoneyEvent::TYPE_MIDDO_REST, OrderMoneyEvent::BUCKET_MIDDO_RETAINED, (int) $breakdown['middo_rest_amount'], [
            'channel' => 'accrual',
            'description' => 'Middo rest after partner shares (before/excluding direct-cost memo)',
            'meta' => [
                'food' => (int) $breakdown['food_amount'],
                'food_ex_vat' => (int) $breakdown['food_amount'] - (int) $breakdown['vat_amount'],
                'vat' => (int) $breakdown['vat_amount'],
                'vat_rate_pct' => (float) $breakdown['vat_rate_pct'],
                'charges' => (int) $breakdown['charges_amount'],
                'discount' => (int) $breakdown['discount_amount'],
                'kitchen' => (int) $breakdown['kitchen_share_amount'],
                'delivery' => (int) $breakdown['delivery_share_amount'],
                'direct_cost' => (int) $breakdown['direct_cost_amount'],
            ],
        ]);
    }

    public static function settlePayable(PartnerPayable $payable, ?int $actorId = null, ?string $notes = null, array $options = []): PartnerPayable
    {
        $debitMiddo = $options['debit_middo'] ?? true;
        $debitKitchenLedger = $options['debit_kitchen_ledger'] ?? true;
        $debitRiderLedger = $options['debit_rider_ledger'] ?? false;

        return DB::transaction(function () use ($payable, $actorId, $notes, $debitMiddo, $debitKitchenLedger, $debitRiderLedger) {
            /** @var PartnerPayable $locked */
            $locked = PartnerPayable::query()->whereKey($payable->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpen()) {
                throw new \RuntimeException('Payable is not open.');
            }

            $entry = null;
            if ($debitMiddo) {
                $entry = MiddoCashLedger::debit(
                    (int) $locked->amount,
                    'partner_payable_settled',
                    PartnerPayable::class,
                    $locked->id,
                    ucfirst($locked->beneficiary_role).' payable settled for order #'.$locked->order_id,
                    $actorId
                );
            }

            if (
                $debitKitchenLedger
                && $locked->beneficiary_role === PartnerPayable::ROLE_KITCHEN
                && $locked->beneficiary_user_id
            ) {
                KitchenAccountLedger::debit(
                    (int) $locked->beneficiary_user_id,
                    (int) $locked->amount,
                    'payable_settled',
                    PartnerPayable::class,
                    $locked->id,
                    'Kitchen share paid for order #'.$locked->order_id,
                    $actorId
                );
            }

            if (
                $debitRiderLedger
                && $locked->beneficiary_role === PartnerPayable::ROLE_DELIVERY
                && $locked->beneficiary_user_id
                && Schema::hasTable('rider_account_ledger')
            ) {
                RiderAccountLedger::debit(
                    (int) $locked->beneficiary_user_id,
                    (int) $locked->amount,
                    'commission_settled_in_kind',
                    PartnerPayable::class,
                    $locked->id,
                    'Commission kept from cash for order #'.$locked->order_id,
                    $actorId
                );
            }

            $locked->update([
                'status' => PartnerPayable::STATUS_SETTLED,
                'settled_at' => now(),
                'settled_by' => $actorId,
                'middo_cash_ledger_entry_id' => $entry?->id,
                'notes' => $notes,
            ]);

            $order = Order::query()->find($locked->order_id);
            if ($order && $entry) {
                self::write($order, OrderMoneyEvent::TYPE_PAYABLE_SETTLED, OrderMoneyEvent::BUCKET_MIDDO_CASH, -1 * (int) $locked->amount, [
                    'channel' => 'settlement',
                    'description' => ucfirst($locked->beneficiary_role).' share paid from Middo cash',
                    'middo_cash_balance_after' => (int) $entry->balance_after,
                    'reference' => $locked,
                    'meta' => [
                        'beneficiary_role' => $locked->beneficiary_role,
                        'beneficiary_user_id' => $locked->beneficiary_user_id,
                    ],
                    'created_by' => $actorId,
                ]);
            } elseif ($order && $debitRiderLedger && ! $debitMiddo) {
                self::write($order, OrderMoneyEvent::TYPE_PAYABLE_SETTLED, OrderMoneyEvent::BUCKET_DELIVERY_PAYABLE, (int) $locked->amount, [
                    'channel' => 'in_kind_from_cash',
                    'description' => 'Delivery commission settled in-kind from rider cash',
                    'reference' => $locked,
                    'meta' => [
                        'beneficiary_role' => $locked->beneficiary_role,
                        'beneficiary_user_id' => $locked->beneficiary_user_id,
                        'settlement' => 'in_kind_from_cash',
                    ],
                    'created_by' => $actorId,
                ]);
            }

            return $locked->fresh();
        });
    }

    /**
     * After COD collect: settle open lunch commission from the cash float so it is
     * not also withdrawable. Returns the commission amount settled (0 if none).
     */
    public static function settleDeliveryCommissionFromCash(Order $order, User $rider, int $cashCollected): int
    {
        if ($cashCollected < 1 || ! Schema::hasTable('partner_payables')) {
            return 0;
        }

        $payable = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
            ->where('beneficiary_user_id', $rider->id)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->lockForUpdate()
            ->first();

        if (! $payable) {
            return 0;
        }

        $commission = (int) $payable->amount;
        if ($commission < 1) {
            return 0;
        }

        // Only settle what cash can cover; leave remainder open/withdrawable.
        if ($commission > $cashCollected) {
            $settle = $cashCollected;
            $payable->update(['amount' => $commission - $settle]);
            if (Schema::hasTable('rider_account_ledger')) {
                RiderAccountLedger::debit(
                    (int) $rider->id,
                    $settle,
                    'commission_settled_in_kind',
                    PartnerPayable::class,
                    $payable->id,
                    'Partial commission kept from cash for order #'.$order->id,
                    $rider->id
                );
            }
            self::write($order, OrderMoneyEvent::TYPE_PAYABLE_SETTLED, OrderMoneyEvent::BUCKET_DELIVERY_PAYABLE, $settle, [
                'channel' => 'in_kind_from_cash',
                'description' => 'Partial delivery commission settled in-kind from rider cash',
                'reference' => $payable,
                'meta' => [
                    'settlement' => 'in_kind_from_cash_partial',
                    'remaining_open' => $commission - $settle,
                ],
                'created_by' => $rider->id,
            ]);

            return $settle;
        }

        self::settlePayable($payable, $rider->id, 'settled_in_kind_from_cash', [
            'debit_middo' => false,
            'debit_kitchen_ledger' => false,
            'debit_rider_ledger' => true,
        ]);

        return $commission;
    }

    /**
     * Rider Due cash accepted by Middo/ops (not kitchen).
     */
    public static function recordCashHandoverToMiddo(CashHandover $handover, ?int $actorId = null): void
    {
        if (! Schema::hasTable('order_money_events')) {
            return;
        }

        $handover->loadMissing('items.order');
        $middoBalance = MiddoCashLedger::balance();

        foreach ($handover->items as $item) {
            $order = $item->order;
            if (! $order) {
                continue;
            }
            $amount = (int) $item->amount;
            if ($amount < 1) {
                continue;
            }

            self::write($order, OrderMoneyEvent::TYPE_CASH_TO_MIDDO, OrderMoneyEvent::BUCKET_MIDDO_CASH, $amount, [
                'channel' => 'cash',
                'description' => "Due cash handed to Middo (handover #{$handover->id})",
                'middo_cash_balance_after' => $middoBalance,
                'reference' => $handover,
                'meta' => [
                    'handover_id' => $handover->id,
                    'rider_id' => $handover->rider_id,
                    'target' => CashHandover::TARGET_MIDDO,
                ],
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * Build a readable flow tree for admin UI.
     *
     * @return array{
     *   summary: array<string,int|string|null>,
     *   billing: list<array<string,mixed>>,
     *   shares: list<array<string,mixed>>,
     *   movements: list<array<string,mixed>>,
     *   payables: list<array<string,mixed>>,
     *   middo_cash_balance: int
     * }
     */
    public static function treeForOrder(Order $order): array
    {
        $order->loadMissing(['menuItem', 'moneyEvents', 'partnerPayables.beneficiary']);

        if ($order->moneyEvents->isEmpty() && Schema::hasTable('order_money_events')) {
            // Legacy orders: synthesize a read-only tree from current columns.
            return self::syntheticTree($order);
        }

        $events = $order->moneyEvents->sortBy('id')->values();

        $billing = [];
        $shares = [];
        $movements = [];

        foreach ($events as $event) {
            $row = [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'bucket' => $event->bucket,
                'amount' => (int) $event->amount,
                'channel' => $event->channel,
                'description' => $event->description,
                'middo_cash_balance_after' => $event->middo_cash_balance_after,
                'at' => $event->created_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
            ];

            if (in_array($event->event_type, [
                OrderMoneyEvent::TYPE_PLACED,
                OrderMoneyEvent::TYPE_CHARGE,
                OrderMoneyEvent::TYPE_DISCOUNT,
                OrderMoneyEvent::TYPE_VAT,
            ], true)) {
                $billing[] = $row;
            } elseif (in_array($event->event_type, [
                OrderMoneyEvent::TYPE_KITCHEN_SHARE,
                OrderMoneyEvent::TYPE_DELIVERY_SHARE,
                OrderMoneyEvent::TYPE_DIRECT_COST,
                OrderMoneyEvent::TYPE_MIDDO_REST,
            ], true)) {
                $shares[] = $row;
            } else {
                $movements[] = $row;
            }
        }

        $payables = $order->partnerPayables->map(fn (PartnerPayable $p) => [
            'id' => $p->id,
            'role' => $p->beneficiary_role,
            'amount' => (int) $p->amount,
            'status' => $p->status,
            'beneficiary' => $p->beneficiary
                ? trim(($p->beneficiary->first_name ?? '').' '.($p->beneficiary->last_name ?? ''))
                : null,
            'settled_at' => $p->settled_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
        ])->values()->all();

        $food = (int) ($order->food_amount ?: max(0, (int) $order->total_amount - (int) ($order->charges_amount ?? 0)));
        $vat = (int) ($order->vat_amount ?? 0);
        $deliveryNet = (int) ($order->delivery_charge_amount ?? 0);
        $deliveryVat = (int) ($order->delivery_vat_amount ?? 0);

        return [
            'summary' => [
                'total' => (int) $order->total_amount,
                'food' => $food,
                'food_ex_vat' => max(0, $food - $vat),
                'vat' => $vat,
                'vat_rate_pct' => (float) ($order->vat_rate_pct ?? 0),
                'charges' => (int) ($order->charges_amount ?? 0),
                'delivery_charge' => $deliveryNet,
                'delivery_discount' => (int) ($order->delivery_discount_amount ?? 0),
                'other_charges' => (int) ($order->other_charges_amount ?? 0),
                'delivery_vat' => $deliveryVat,
                'delivery_vat_rate_pct' => (float) ($order->delivery_vat_rate_pct ?? 0),
                'delivery_ex_vat' => max(0, $deliveryNet - $deliveryVat),
                'discount' => (int) ($order->discount_amount ?? 0),
                'paid' => (int) $order->amount_paid,
                'due' => $order->amountDue(),
                'cash_collected' => $order->cashCollectedAmount(),
                'kitchen_share' => (int) ($order->kitchen_share_amount ?? 0),
                'delivery_share' => (int) ($order->delivery_share_amount ?? 0),
                'direct_cost' => (int) ($order->direct_cost_amount ?? 0),
                'middo_rest' => (int) ($order->middo_rest_amount ?? 0),
                'payment_method' => $order->paymentMethodLabel(),
            ],
            'billing' => $billing,
            'shares' => $shares,
            'movements' => $movements,
            'payables' => $payables,
            'middo_cash_balance' => MiddoCashLedger::balance(),
        ];
    }

    /**
     * @return array{
     *   food_amount:int,
     *   vat_rate_pct:float,
     *   vat_amount:int,
     *   charges_amount:int,
     *   delivery_charge_amount:int,
     *   other_charges_amount:int,
     *   delivery_discount_amount:int,
     *   delivery_vat_rate_pct:float,
     *   delivery_vat_amount:int,
     *   discount_amount:int,
     *   kitchen_share_amount:int,
     *   delivery_share_amount:int,
     *   direct_cost_amount:int,
     *   middo_rest_amount:int
     * }
     */
    public static function computeBreakdown(Order $order): array
    {
        $qty = max(1, (int) $order->quantity);
        $menu = $order->menuItem instanceof MenuItem ? $order->menuItem : null;

        $charges = (int) ($order->charges_amount ?? 0);
        $discount = (int) ($order->discount_amount ?? 0);
        $total = (int) $order->total_amount;

        // Prefer explicit food_amount; else derive from total - charges (+ discount if total is post-discount).
        $food = (int) ($order->food_amount ?? 0);
        if ($food < 1) {
            $food = max(0, $total - $charges);
            if ($menu) {
                $food = (int) $menu->price * $qty;
            }
        }

        // Snapshot: first breakdown (food not yet saved) uses Settings; later recomputes keep order.vat_rate_pct.
        $rate = MiddoSettings::vatRatePct();
        if (
            Schema::hasColumn('orders', 'vat_rate_pct')
            && $order->exists
            && (int) ($order->food_amount ?? 0) > 0
        ) {
            $rate = (float) ($order->vat_rate_pct ?? 0);
        }

        $rate = max(0.0, min(100.0, $rate));
        $foodEx = $rate > 0 ? (int) round($food / (1 + ($rate / 100))) : $food;
        $vat = max(0, $food - $foodEx);

        $deliveryChargeAmount = (int) ($order->delivery_charge_amount ?? 0);
        $otherChargesAmount = (int) ($order->other_charges_amount ?? 0);
        $deliveryDiscountAmount = (int) ($order->delivery_discount_amount ?? 0);
        $deliveryVatRate = (float) ($order->delivery_vat_rate_pct ?? 0);
        $deliveryVatAmount = (int) ($order->delivery_vat_amount ?? 0);

        // Derive delivery split from order_charges when not yet snapshotted on the order.
        if (
            Schema::hasColumn('orders', 'delivery_charge_amount')
            && $order->exists
            && $deliveryChargeAmount < 1
            && $otherChargesAmount < 1
            && $charges > 0
        ) {
            $order->loadMissing('appliedCharges', 'coupon');
            $lines = $order->appliedCharges->map(fn ($row) => [
                'charge_id' => $row->charge_id,
                'category' => $row->category,
                'amount' => (int) $row->amount,
            ])->all();

            if ($lines !== []) {
                $quoted = DeliveryChargeVat::quote(
                    $order->coupon,
                    $discount,
                    $lines,
                    MiddoSettings::deliveryVatRatePct()
                );
                $deliveryChargeAmount = $quoted['delivery_net'];
                $otherChargesAmount = $quoted['other_gross'];
                $deliveryDiscountAmount = $quoted['delivery_discount'];
                $deliveryVatRate = $quoted['delivery_vat_rate_pct'];
                $deliveryVatAmount = $quoted['delivery_vat_amount'];
            } else {
                $otherChargesAmount = $charges;
                $deliveryVatRate = MiddoSettings::deliveryVatRatePct();
            }
        } elseif ($deliveryChargeAmount > 0 || $deliveryDiscountAmount > 0) {
            // Keep snapshotted rate when present; otherwise unbundle with current/settings rate.
            if ($deliveryVatRate <= 0 && $order->exists && (int) ($order->delivery_charge_amount ?? 0) > 0) {
                $deliveryVatRate = (float) ($order->delivery_vat_rate_pct ?? 0);
            }
            if ($deliveryVatRate <= 0) {
                $deliveryVatRate = MiddoSettings::deliveryVatRatePct();
            }
            if ($deliveryVatAmount < 1 && $deliveryChargeAmount > 0) {
                $unbundled = DeliveryChargeVat::unbundleInclusive($deliveryChargeAmount, $deliveryVatRate);
                $deliveryVatRate = $unbundled['rate'];
                $deliveryVatAmount = $unbundled['vat'];
            }
        } elseif ($charges > 0 && $otherChargesAmount < 1 && $deliveryChargeAmount < 1) {
            $otherChargesAmount = $charges;
        }

        if ($charges < 1) {
            $charges = $deliveryChargeAmount + $deliveryDiscountAmount + $otherChargesAmount;
        }

        $kitchenUnit = (int) ($menu?->kitchen_commission ?? 0);
        $deliveryUnit = (int) ($menu?->delivery_commission ?? 0);
        $directUnit = (int) ($menu?->meals_cost ?? 0) + (int) ($menu?->other_cost ?? 0);

        $kitchen = $kitchenUnit * $qty;
        $delivery = $deliveryUnit * $qty;
        $direct = $directUnit * $qty;

        // Cap partner shares against inclusive customer bill (VAT stays in the bill).
        $billNet = max(0, $food + $charges - $discount);
        $partnerTotal = $kitchen + $delivery;
        if ($partnerTotal > $billNet && $partnerTotal > 0) {
            $scaledKitchen = (int) floor(($kitchen * $billNet) / $partnerTotal);
            $kitchen = $scaledKitchen;
            $delivery = $billNet - $scaledKitchen;
        }
        // Middo rest uses food + delivery ex-VAT so VAT is tax payable, not Middo margin.
        $middoRest = max(0, $billNet - $vat - $deliveryVatAmount - $kitchen - $delivery);

        return [
            'food_amount' => $food,
            'vat_rate_pct' => $rate,
            'vat_amount' => $vat,
            'charges_amount' => $charges,
            'delivery_charge_amount' => $deliveryChargeAmount,
            'other_charges_amount' => $otherChargesAmount,
            'delivery_discount_amount' => $deliveryDiscountAmount,
            'delivery_vat_rate_pct' => $deliveryVatRate,
            'delivery_vat_amount' => $deliveryVatAmount,
            'discount_amount' => $discount,
            'kitchen_share_amount' => $kitchen,
            'delivery_share_amount' => $delivery,
            'direct_cost_amount' => $direct,
            'middo_rest_amount' => $middoRest,
        ];
    }

    protected static function unitFoodPrice(Order $order): int
    {
        $qty = max(1, (int) $order->quantity);
        if ($order->menuItem) {
            return (int) $order->menuItem->price;
        }

        return (int) floor(((int) ($order->food_amount ?: $order->total_amount)) / $qty);
    }

    protected static function paymentChannel(Order $order): string
    {
        return match ($order->payment_method) {
            'balance' => 'wallet',
            'gateway' => 'gateway',
            'cash_on_delivery' => 'cash',
            default => (string) ($order->payment_method ?: 'system'),
        };
    }

    /**
     * @param  array{channel?:?string,description?:?string,middo_cash_balance_after?:?int,reference?:mixed,meta?:?array,created_by?:?int}  $opts
     */
    protected static function write(Order $order, string $type, string $bucket, int $amount, array $opts = []): OrderMoneyEvent
    {
        $reference = $opts['reference'] ?? null;

        return OrderMoneyEvent::create([
            'order_id' => $order->id,
            'event_type' => $type,
            'bucket' => $bucket,
            'amount' => $amount,
            'middo_cash_balance_after' => $opts['middo_cash_balance_after'] ?? null,
            'channel' => $opts['channel'] ?? null,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'meta' => $opts['meta'] ?? null,
            'description' => $opts['description'] ?? null,
            'created_by' => $opts['created_by'] ?? Auth::id() ?? $order->updated_by ?? $order->created_by,
        ]);
    }

    protected static function upsertPayable(Order $order, string $role, ?int $beneficiaryId, int $amount): void
    {
        if ($amount < 1 || ! Schema::hasTable('partner_payables')) {
            return;
        }

        PartnerPayable::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'beneficiary_role' => $role,
            ],
            [
                'beneficiary_user_id' => $beneficiaryId,
                'amount' => $amount,
                'status' => PartnerPayable::STATUS_OPEN,
                'settled_at' => null,
                'settled_by' => null,
                'middo_cash_ledger_entry_id' => null,
            ]
        );
    }

    protected static function voidOpenPayables(Order $order): void
    {
        if (! Schema::hasTable('partner_payables')) {
            return;
        }

        $open = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->get();

        foreach ($open as $payable) {
            if (
                $payable->beneficiary_role === PartnerPayable::ROLE_KITCHEN
                && $payable->beneficiary_user_id
                && Schema::hasTable('kitchen_account_ledger')
            ) {
                KitchenAccountLedger::debit(
                    (int) $payable->beneficiary_user_id,
                    (int) $payable->amount,
                    'share_voided',
                    PartnerPayable::class,
                    $payable->id,
                    'Kitchen share voided for cancelled order #'.$order->id,
                    $order->updated_by
                );
            }

            if (
                $payable->beneficiary_role === PartnerPayable::ROLE_DELIVERY
                && $payable->beneficiary_user_id
                && Schema::hasTable('rider_account_ledger')
            ) {
                RiderAccountLedger::debit(
                    (int) $payable->beneficiary_user_id,
                    (int) $payable->amount,
                    'share_voided',
                    PartnerPayable::class,
                    $payable->id,
                    'Delivery share voided for cancelled order #'.$order->id,
                    $order->updated_by
                );
            }
        }

        PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->update(['status' => PartnerPayable::STATUS_VOID]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function syntheticTree(Order $order): array
    {
        $breakdown = self::computeBreakdown($order);

        return [
            'summary' => [
                'total' => (int) $order->total_amount,
                'food' => $breakdown['food_amount'],
                'food_ex_vat' => max(0, $breakdown['food_amount'] - $breakdown['vat_amount']),
                'vat' => $breakdown['vat_amount'],
                'vat_rate_pct' => $breakdown['vat_rate_pct'],
                'charges' => $breakdown['charges_amount'],
                'delivery_charge' => $breakdown['delivery_charge_amount'],
                'delivery_discount' => $breakdown['delivery_discount_amount'],
                'other_charges' => $breakdown['other_charges_amount'],
                'delivery_vat' => $breakdown['delivery_vat_amount'],
                'delivery_vat_rate_pct' => $breakdown['delivery_vat_rate_pct'],
                'delivery_ex_vat' => max(0, $breakdown['delivery_charge_amount'] - $breakdown['delivery_vat_amount']),
                'discount' => $breakdown['discount_amount'],
                'paid' => (int) $order->amount_paid,
                'due' => $order->amountDue(),
                'cash_collected' => $order->cashCollectedAmount(),
                'kitchen_share' => $breakdown['kitchen_share_amount'],
                'delivery_share' => $breakdown['delivery_share_amount'],
                'direct_cost' => $breakdown['direct_cost_amount'],
                'middo_rest' => $breakdown['middo_rest_amount'],
                'payment_method' => $order->paymentMethodLabel(),
            ],
            'billing' => [
                [
                    'event_type' => 'placed',
                    'amount' => $breakdown['food_amount'],
                    'description' => 'Food revenue (estimated from current menu, VAT-inclusive)',
                    'middo_cash_balance_after' => null,
                    'at' => null,
                ],
            ],
            'shares' => [],
            'movements' => [],
            'payables' => [],
            'middo_cash_balance' => MiddoCashLedger::balance(),
            'legacy' => true,
        ];
    }
}
