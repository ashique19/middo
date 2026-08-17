<?php

namespace App\Support;

use App\Models\CustomRun;
use App\Models\KitchenWarehouseHandoff;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ops assigns a rider for every run type. Riders never first-claim a pool.
 */
class OpsAssignRider
{
    public static function lunch(Order $order, User $rider, User $actor): Order
    {
        $rider = StaffRiders::assertActiveDelivery($rider);

        return DB::transaction(function () use ($order, $rider, $actor) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->order_status === OrderTransition::PROCESSING && $order->delivery_rider_id === null) {
                $order->update([
                    'delivery_rider_id' => $rider->id,
                    'original_delivery_rider_id' => $order->original_delivery_rider_id ?: $rider->id,
                    'updated_by' => $actor->id,
                ]);
            } elseif ($order->order_status === OrderTransition::READY && $order->delivery_rider_id === null) {
                OrderTransition::apply($order, OrderTransition::RIDER_ASSIGNED, [
                    'delivery_rider_id' => $rider->id,
                    'original_delivery_rider_id' => $order->original_delivery_rider_id ?: $rider->id,
                    'updated_by' => $actor->id,
                ]);
            } elseif ($order->order_status === OrderTransition::PACKED
                && $order->dispatched_at !== null
                && $order->delivery_rider_id === null) {
                $order->update([
                    'delivery_rider_id' => $rider->id,
                    'original_delivery_rider_id' => $order->original_delivery_rider_id ?: $rider->id,
                    'updated_by' => $actor->id,
                ]);
            } elseif ((int) $order->delivery_rider_id === (int) $rider->id) {
                return $order->fresh(['deliveryRider', 'menuItem', 'orderGroup']);
            } else {
                throw new \RuntimeException('This lunch run is not waiting for a rider assignment.');
            }

            $fresh = $order->fresh(['deliveryRider', 'menuItem', 'orderGroup']);
            StaffAlerts::notifyRiderLunchAssigned($fresh);

            return $fresh;
        });
    }

    public static function kitchenToOps(int $boxId, User $rider, User $actor): MiddoBox
    {
        $rider = StaffRiders::assertActiveDelivery($rider);

        return DB::transaction(function () use ($boxId, $rider, $actor) {
            $handoff = KitchenWarehouseHandoff::query()
                ->with(['kitchen', 'box'])
                ->where('middo_box_id', $boxId)
                ->where('status', KitchenWarehouseHandoff::STATUS_RUN_REQUESTED)
                ->whereNull('rider_id')
                ->lockForUpdate()
                ->first();

            if (! $handoff) {
                throw new \RuntimeException('This kitchen→ops run is not waiting for a rider assignment.');
            }

            $box = MiddoBox::query()->whereKey($boxId)->lockForUpdate()->firstOrFail();
            if (! $box->isAtKitchen((int) $handoff->kitchen_id)) {
                throw new \RuntimeException('This box is not available at the kitchen.');
            }

            $handoff->update([
                'rider_id' => $rider->id,
                'status' => KitchenWarehouseHandoff::STATUS_RUN_CLAIMED,
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'assigned_at_kitchen',
                'log_action' => 'ops_assigned_warehouse_run',
                'notes' => $actor->name.' assigned '.$rider->name.' to kitchen→ops return',
                'performed_by' => $actor->id,
            ]);

            $fresh = $box->fresh(['warehouseHandoff.rider']);
            $kitchen = $handoff->kitchen ?? User::query()->find($handoff->kitchen_id);
            StaffAlerts::notifyRiderKitchenToOpsAssigned($rider, $kitchen, [$fresh]);
            if ($kitchen) {
                StaffAlerts::notifyKitchenWarehouseRunClaimed($rider, $kitchen, [$fresh]);
            }

            return $fresh;
        });
    }

    public static function emptyBoxPickup(MiddoBox $box, User $rider, User $actor, ?int $kitchenId = null): MiddoBox
    {
        $rider = StaffRiders::assertActiveDelivery($rider);

        if (! Schema::hasColumn('middo_boxes', 'pickup_rider_id')) {
            throw new \RuntimeException('Empty-box rider assignment is not installed yet. Run migrations.');
        }

        return DB::transaction(function () use ($box, $rider, $actor, $kitchenId) {
            $box = MiddoBox::query()->with(['heldByUser.role', 'orderMiddoBoxes.order.orderGroup'])->whereKey($box->id)->lockForUpdate()->firstOrFail();

            if ($box->heldByUser?->role?->name !== 'corporate') {
                throw new \RuntimeException('This box is not held by a corporate customer.');
            }

            $destKitchenId = $kitchenId
                ?: $box->return_kitchen_id
                ?: $box->orderMiddoBoxes->last()?->order?->orderGroup?->kitchen_id;

            if (! $destKitchenId) {
                throw new \RuntimeException('Pick a destination kitchen for this empty-box return.');
            }

            $kitchen = User::query()->whereKey($destKitchenId)->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))->first();
            if (! $kitchen) {
                throw new \RuntimeException('Destination kitchen is invalid.');
            }

            $payload = [
                'pickup_rider_id' => $rider->id,
                'last_scanned_at' => now(),
            ];
            if (Schema::hasColumn('middo_boxes', 'return_kitchen_id')) {
                $payload['return_kitchen_id'] = $kitchen->id;
            }

            $box->update($payload);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'assigned_at_kitchen',
                'log_action' => 'ops_assigned_empty_box_pickup',
                'notes' => $actor->name.' assigned '.$rider->name.' to collect empty box for '.$kitchen->name,
                'performed_by' => $actor->id,
            ]);

            $fresh = $box->fresh(['heldByUser', 'pickupRider']);
            StaffAlerts::notifyRiderEmptyBoxAssigned($fresh, $rider);

            return $fresh;
        });
    }

    public static function customRun(CustomRun $run, User $rider, User $actor): CustomRun
    {
        $rider = StaffRiders::assertActiveDelivery($rider);

        if (! $run->isPending()) {
            throw new \RuntimeException('Only pending custom runs can be assigned.');
        }

        if ($run->area_id && ! $rider->servesArea((int) $run->area_id)) {
            throw new \RuntimeException('Selected rider does not serve that area.');
        }

        $run->update(['rider_user_id' => $rider->id]);
        $fresh = $run->fresh(['rider', 'area']);
        StaffAlerts::notifyRidersCustomRun($fresh);

        return $fresh;
    }
}
