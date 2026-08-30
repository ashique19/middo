<?php

namespace App\Support;

use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderMiddoBox;
use Illuminate\Support\Facades\DB;

/**
 * Kitchen pack/dispatch: attach Middo boxes and move order to packed.
 */
class OrderKitchenDispatch
{
    /**
     * @param  list<int>  $boxIds
     */
    public static function dispatchWithBoxes(Order $order, int $kitchenId, array $boxIds): Order
    {
        $boxIds = array_values(array_unique(array_map('intval', $boxIds)));

        return DB::transaction(function () use ($order, $kitchenId, $boxIds) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new \RuntimeException('Order not found for your kitchen.');
            }

            $groupKitchenId = DB::table('order_group_orders')
                ->join('order_groups', 'order_groups.id', '=', 'order_group_orders.order_group_id')
                ->where('order_group_orders.order_id', $locked->id)
                ->value('order_groups.kitchen_id');

            if ($groupKitchenId === null || (int) $groupKitchenId !== $kitchenId) {
                throw new \RuntimeException('Order not found for your kitchen.');
            }

            if (! $locked->isRiderAssignedAwaitingDispatch()) {
                throw new \RuntimeException(
                    $locked->order_status === OrderTransition::READY
                        ? 'A rider must accept this order before you can dispatch.'
                        : 'This order is no longer ready to dispatch.'
                );
            }

            if ($locked->dispatched_at !== null) {
                throw new \RuntimeException('This order has already been dispatched.');
            }

            $required = (int) $locked->quantity;
            if (count($boxIds) !== $required) {
                throw new \RuntimeException("Select exactly {$required} box(es) for this order.");
            }

            $boxes = MiddoBox::query()
                ->whereIn('id', $boxIds)
                ->lockForUpdate()
                ->get();

            if ($boxes->count() !== count($boxIds)) {
                throw new \RuntimeException('One or more selected boxes are unavailable.');
            }

            foreach ($boxes as $box) {
                if (! $box->isAtKitchen($kitchenId) || $box->isDamaged()) {
                    throw new \RuntimeException("{$box->qr_code_id} is not available in your kitchen inventory.");
                }

                if (OrderMiddoBox::query()->where('middo_box_id', $box->id)->exists()) {
                    throw new \RuntimeException("{$box->qr_code_id} is already reserved for another order.");
                }

                OrderMiddoBox::create([
                    'order_id' => $locked->id,
                    'middo_box_id' => $box->id,
                ]);

                $box->update([
                    'last_scanned_at' => now(),
                ]);
            }

            OrderTransition::apply($locked, OrderTransition::PACKED, [
                'dispatched_at' => now(),
                'updated_by' => $kitchenId,
            ]);

            $fresh = $locked->fresh(['menuItem', 'orderGroup', 'deliveryRider', 'area', 'packageSubscription.package']);
            StaffAlerts::notifyRiderLunchPacked($fresh);

            return $fresh;
        });
    }

    /**
     * @return list<array{id: int, qr_code_id: string|null}>
     */
    public static function availableBoxesForKitchen(int $kitchenId): array
    {
        return MiddoBox::query()
            ->sendableAtKitchen($kitchenId)
            ->orderBy('qr_code_id')
            ->get()
            ->map(fn (MiddoBox $box) => [
                'id' => $box->id,
                'qr_code_id' => $box->qr_code_id,
            ])
            ->all();
    }
}
