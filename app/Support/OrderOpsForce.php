<?php

namespace App\Support;

use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\PartnerPayable;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ops/admin force tools for lunch orders (audited). Does not widen OrderTransition::ALLOWED.
 */
class OrderOpsForce
{
    /**
     * Cancel before kitchen packed/dispatched: pending|processing|ready.
     *
     * @return array{order: Order, refunded_amount: int}
     */
    public static function cancelBeforePacked(Order $order, User $actor, ?string $reason = null): array
    {
        self::assertStaff($actor);

        return DB::transaction(function () use ($order, $actor, $reason) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $status = (string) $locked->order_status;

            if (! in_array($status, ['pending', OrderTransition::PROCESSING, OrderTransition::READY], true)) {
                throw new \RuntimeException(
                    'Ops cancel is only allowed before packed (pending / processing / ready).'
                );
            }

            if ($locked->dispatched_at !== null) {
                throw new \RuntimeException('Order is already kitchen-dispatched; cancel is blocked.');
            }

            $refund = (int) ($locked->amount_paid ?? 0);
            $corporate = User::query()->whereKey($locked->user_id)->lockForUpdate()->first();

            if ($refund > 0 && $corporate) {
                WalletLedger::credit(
                    $corporate,
                    $refund,
                    WalletTransaction::TYPE_REFUND,
                    'Ops refund for cancelled order #'.$locked->id.($reason ? ': '.$reason : ''),
                    $locked
                );
            }

            app(OrderGroupManager::class)->ungroup((int) $locked->id);

            self::forceStatus($locked, OrderTransition::CANCELLED, [
                'updated_by' => $actor->id,
            ], $actor, $reason ?: 'Ops cancelled before packed');

            // Money void runs via OrderMoneyFlow when status becomes cancelled.
            return [
                'order' => $locked->fresh(),
                'refunded_amount' => $refund,
            ];
        });
    }

    /**
     * Release rider from on_the_way → packed (open pool). Voids open delivery payable + returns boxes to kitchen.
     */
    public static function releaseRiderToPacked(Order $order, User $actor, ?string $reason = null): Order
    {
        self::assertStaff($actor);

        return DB::transaction(function () use ($order, $actor, $reason) {
            $locked = Order::query()
                ->with(['middoBoxes', 'orderGroup'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $locked->order_status !== OrderTransition::ON_THE_WAY_TO_DELIVERY) {
                throw new \RuntimeException('Release is only available while the order is on the way to delivery.');
            }

            if ($locked->delivery_rider_id === null) {
                throw new \RuntimeException('Order has no delivery rider to release.');
            }

            $kitchenId = $locked->orderGroup?->kitchen_id;
            $boxes = $locked->middoBoxes()->lockForUpdate()->get();

            foreach ($boxes as $box) {
                $box->update([
                    'held_by_user_id' => $kitchenId,
                    'kitchen_id' => $kitchenId,
                    'asset_status' => 'active',
                    'last_scanned_at' => now(),
                ]);

                MiddoBoxLog::create([
                    'order_id' => $locked->id,
                    'middo_box_id' => $box->id,
                    'custody_status' => $kitchenId ? 'at_kitchen' : 'in_transit',
                    'log_action' => 'ops_released_rider_returned_to_kitchen',
                ]);
            }

            self::voidOpenDeliveryPayable($locked, $actor->id);

            self::forceStatus($locked, OrderTransition::PACKED, [
                'delivery_rider_id' => null,
                'updated_by' => $actor->id,
            ], $actor, $reason ?: 'Ops released rider — back to packed awaiting accept');

            return $locked->fresh(['deliveryRider', 'middoBoxes', 'orderGroup.kitchen']);
        });
    }

    protected static function assertStaff(User $actor): void
    {
        $role = $actor->role?->name ?? $actor->loadMissing('role')->role?->name;
        if (! in_array($role, ['admin', 'operation'], true)) {
            throw new \RuntimeException('Only admin or operation can use order force tools.');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function forceStatus(Order $order, string $to, array $attributes, User $actor, string $reason): void
    {
        $from = (string) $order->order_status;

        $order->update([
            ...$attributes,
            'order_status' => $to,
        ]);

        if (Schema::hasTable('order_logs')) {
            OrderLog::create([
                'order_id' => $order->id,
                'event' => 'ops_force_status',
                'performed_by' => $actor->id,
                'metadata' => [
                    'from' => $from,
                    'to' => $to,
                    'reason' => $reason,
                    'force' => true,
                ],
            ]);
        }
    }

    protected static function voidOpenDeliveryPayable(Order $order, ?int $actorId): void
    {
        if (! Schema::hasTable('partner_payables')) {
            return;
        }

        $open = PartnerPayable::query()
            ->where('order_id', $order->id)
            ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->lockForUpdate()
            ->get();

        foreach ($open as $payable) {
            if ($payable->beneficiary_user_id && Schema::hasTable('rider_account_ledger')) {
                RiderAccountLedger::debit(
                    (int) $payable->beneficiary_user_id,
                    (int) $payable->amount,
                    'share_voided',
                    PartnerPayable::class,
                    $payable->id,
                    'Delivery share voided — ops released rider on order #'.$order->id,
                    $actorId
                );
            }
            $payable->update(['status' => PartnerPayable::STATUS_VOID]);
        }
    }
}
