<?php

namespace App\Support;

use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ops-mediated mid-run parcel/custody reassign (A → B).
 *
 * Keeps starter (A) accrued lunch commission; does not void payables.
 * Does not peer-transfer Due/cash. Blocks after cash collection on the order.
 */
class OpsRiderMidRunReassign
{
    public static function reassign(Order $order, User $toRider, User $actor, ?string $reason = null): Order
    {
        self::assertStaff($actor);

        return DB::transaction(function () use ($order, $toRider, $actor, $reason) {
            $locked = Order::query()
                ->with(['middoBoxes', 'orderGroup.area', 'area'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $locked->order_status !== OrderTransition::ON_THE_WAY_TO_DELIVERY) {
                throw new \RuntimeException('Mid-run reassign is only available while the order is on the way to delivery.');
            }

            if ($locked->delivery_rider_id === null) {
                throw new \RuntimeException('Order has no delivery rider to reassign from.');
            }

            if ((int) ($locked->cash_collected ?? 0) > 0) {
                throw new \RuntimeException(
                    'Cannot mid-run reassign after cash has been collected. Due stays with the collecting rider until Middo/kitchen handover.'
                );
            }

            $fromId = (int) $locked->delivery_rider_id;
            $toId = (int) $toRider->id;

            if ($fromId === $toId) {
                throw new \RuntimeException('Choose a different rider than the current assignee.');
            }

            $toRider->loadMissing('role');
            if ($toRider->role?->name !== 'delivery') {
                throw new \RuntimeException('Assignee must be a delivery rider.');
            }

            if (! RiderShift::canAcceptNewRuns($toRider->rider_shift_status ?? null)) {
                throw new \RuntimeException('Rescue rider must be on shift.');
            }

            $areaId = $locked->area_id
                ?? $locked->orderGroup?->area_id
                ?? null;
            if ($areaId && ! $toRider->servesArea((int) $areaId)) {
                throw new \RuntimeException('Selected rider does not serve that area.');
            }

            $boxes = $locked->middoBoxes()->lockForUpdate()->get();
            foreach ($boxes as $box) {
                $box->update([
                    'held_by_user_id' => $toId,
                    'kitchen_id' => null,
                    'asset_status' => 'active',
                    'last_scanned_at' => now(),
                ]);

                MiddoBoxLog::create([
                    'order_id' => $locked->id,
                    'middo_box_id' => $box->id,
                    'custody_status' => 'in_transit',
                    'log_action' => 'ops_mid_run_reassigned',
                ]);
            }

            $originalId = $locked->original_delivery_rider_id
                ? (int) $locked->original_delivery_rider_id
                : $fromId;

            $locked->update([
                'delivery_rider_id' => $toId,
                'original_delivery_rider_id' => $originalId,
                'updated_by' => $actor->id,
            ]);

            if (Schema::hasTable('order_logs')) {
                OrderLog::create([
                    'order_id' => $locked->id,
                    'event' => 'ops_mid_run_reassign',
                    'performed_by' => $actor->id,
                    'metadata' => [
                        'from_rider_id' => $fromId,
                        'to_rider_id' => $toId,
                        'original_delivery_rider_id' => $originalId,
                        'reason' => $reason,
                        'commission_policy' => 'starter_keeps_accrual',
                        'due_policy' => 'stays_until_handover_no_peer_transfer',
                    ],
                ]);
            }

            $rescue = MiddoSettings::midRunRescueCommission();
            if ($rescue > 0) {
                MiddoOperatingCosts::bookRiderCommission(
                    $toRider,
                    DeliveryRunType::MID_RUN_RESCUE,
                    $rescue,
                    Order::class,
                    (int) $locked->id,
                    'Mid-run rescue for order #'.$locked->id,
                    $actor->id
                );
            }

            return $locked->fresh(['deliveryRider', 'originalDeliveryRider', 'middoBoxes', 'orderGroup.kitchen']);
        });
    }

    protected static function assertStaff(User $actor): void
    {
        $role = $actor->role?->name ?? $actor->loadMissing('role')->role?->name;
        if (! in_array($role, ['admin', 'operation'], true)) {
            throw new \RuntimeException('Only admin or operation can mid-run reassign.');
        }
    }
}
