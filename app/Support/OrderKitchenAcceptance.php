<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use Illuminate\Support\Facades\DB;

/**
 * When a kitchen is assigned to an order group, move member orders
 * from pending → processing so corporates (and push) see "Kitchen accepted".
 */
class OrderKitchenAcceptance
{
    public static function markGroupOrdersProcessing(OrderGroup $group, ?int $actorId = null): int
    {
        return DB::transaction(function () use ($group, $actorId) {
            $orders = Order::query()
                ->whereHas('orderGroupOrder', fn ($q) => $q->where('order_group_id', $group->id))
                ->where('order_status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                OrderTransition::apply($order, OrderTransition::PROCESSING, [
                    'updated_by' => $actorId ?? $order->updated_by,
                ]);
            }

            if ($orders->isNotEmpty()) {
                $sample = $orders->first()->fresh(['menuItem', 'orderGroup', 'area']);
                if ($sample) {
                    StaffAlerts::notifyOpsLunchNeedsRider($sample);
                }
            }

            return $orders->count();
        });
    }
}
