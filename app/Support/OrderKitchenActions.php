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
        return DB::transaction(function () use ($order, $actor) {
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

            OrderTransition::apply($locked, OrderTransition::READY, [
                'updated_by' => $actor->id,
            ]);

            if (OrderLens::isStaff($role)) {
                OrderLogWrite::opsIntervene($locked, $actor, 'mark_ready', [
                    'from' => OrderTransition::PROCESSING,
                    'to' => OrderTransition::READY,
                    'lens' => OrderLens::KITCHEN,
                ]);
            }

            return $locked->fresh();
        });
    }
}
