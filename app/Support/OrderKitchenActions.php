<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kitchen-facing order mutations usable by kitchen (own) or ops/admin (intervene).
 */
class OrderKitchenActions
{
    public static function markReady(Order $order, User $actor): Order
    {
        $fresh = DB::transaction(function () use ($order, $actor) {
            $locked = Order::query()
                ->with('orderGroup')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $role = $actor->role?->name ?? $actor->loadMissing('role')->role?->name;
            $kitchenId = $locked->orderGroup?->kitchen_id;

            if ($role === 'kitchen') {
                if ((int) $kitchenId !== (int) $actor->id) {
                    throw new \RuntimeException('Order not found for your kitchen.');
                }
            } elseif (! OrderLens::isStaff($role)) {
                throw new \RuntimeException('Not allowed to mark this order ready.');
            }

            if ($kitchenId === null) {
                throw new \RuntimeException('Order has no kitchen assigned.');
            }

            $from = (string) $locked->order_status;
            $alreadyAssigned = (int) ($locked->delivery_rider_id ?? 0) > 0;
            $to = $alreadyAssigned ? OrderTransition::RIDER_ASSIGNED : OrderTransition::READY;

            OrderTransition::apply($locked, $to, [
                'updated_by' => $actor->id,
            ]);

            if (OrderLens::isStaff($role)) {
                OrderLogWrite::opsIntervene($locked, $actor, 'mark_ready', [
                    'from' => $from,
                    'to' => $to,
                    'lens' => OrderLens::KITCHEN,
                ]);
            }

            return $locked->fresh(['menuItem', 'orderGroup', 'area', 'deliveryRider']);
        });

        if ($fresh->order_status === OrderTransition::READY) {
            StaffAlerts::notifyOpsLunchNeedsRider($fresh);
        }

        return $fresh;
    }
}
