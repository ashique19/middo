<?php

namespace App\Support;

use App\Models\KitchenWarehouseHandoff;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MiddoBoxKitchenActions
{
    public static function markDamaged(MiddoBox $box, int $kitchenId, ?string $notes = null): MiddoBox
    {
        return DB::transaction(function () use ($box, $kitchenId, $notes) {
            $box = MiddoBox::query()->whereKey($box->id)->lockForUpdate()->firstOrFail();

            if (! $box->isAtKitchen($kitchenId)) {
                throw new \RuntimeException('This box is not in your kitchen inventory.');
            }

            if ($box->orderMiddoBoxes()->exists()) {
                throw new \RuntimeException('This box is reserved for a dispatched order.');
            }

            if ($box->asset_status === 'damaged') {
                throw new \RuntimeException('This box is already marked damaged.');
            }

            if ($box->hasOpenWarehouseHandoff()) {
                throw new \RuntimeException('Cancel the warehouse run before marking this box damaged.');
            }

            $box->update([
                'asset_status' => 'damaged',
                'last_scanned_at' => now(),
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'assigned_at_kitchen',
                'log_action' => 'marked_damaged_at_kitchen',
                'notes' => self::normalizeNotes($notes),
                'performed_by' => $kitchenId,
            ]);

            return $box->fresh();
        });
    }

    /**
     * Empty-box return entry point.
     * Via-rider on → mark ready to ship (area riders notified).
     * Via-rider off → teleport to warehouse inventory.
     */
    public static function sendToWarehouse(MiddoBox $box, int $kitchenId): MiddoBox
    {
        if (MiddoSettings::kitchenToOpsViaRider()) {
            return self::markReadyToShip($box, $kitchenId);
        }

        return self::teleportToWarehouse($box, $kitchenId);
    }

    public static function teleportToWarehouse(MiddoBox $box, int $kitchenId): MiddoBox
    {
        return DB::transaction(function () use ($box, $kitchenId) {
            $box = MiddoBox::query()->whereKey($box->id)->lockForUpdate()->firstOrFail();

            if (! $box->isAtKitchen($kitchenId)) {
                throw new \RuntimeException('This box is not in your kitchen inventory.');
            }

            if ($box->orderMiddoBoxes()->exists()) {
                throw new \RuntimeException('This box is reserved for a dispatched order.');
            }

            if ($box->asset_status === 'damaged') {
                throw new \RuntimeException('Use “Send damaged to Middo” for damaged boxes.');
            }

            if ($box->hasOpenWarehouseHandoff()) {
                throw new \RuntimeException('This box is already on a warehouse return run.');
            }

            $box->update([
                'kitchen_id' => null,
                'held_by_user_id' => null,
                'asset_status' => 'at_middo_warehouse',
                'last_scanned_at' => now(),
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'warehouse',
                'log_action' => 'returned_to_warehouse',
                'performed_by' => $kitchenId,
            ]);

            return $box->fresh();
        });
    }

    /**
     * Kitchen marks empty box ready to ship → active riders notified to claim the run.
     *
     * @param  bool  $damagedReturn  When true, box must already be marked damaged (kitchen→ops damaged path).
     */
    public static function markReadyToShip(MiddoBox $box, int $kitchenId, bool $damagedReturn = false, ?string $notes = null): MiddoBox
    {
        $fresh = DB::transaction(function () use ($box, $kitchenId, $damagedReturn, $notes) {
            if (! Schema::hasTable('kitchen_warehouse_handoffs')) {
                throw new \RuntimeException(
                    'Kitchen→ops rider handoff is not installed yet. Run migrations (kitchen_warehouse_handoffs).'
                );
            }

            $box = MiddoBox::query()->with('warehouseHandoff')->whereKey($box->id)->lockForUpdate()->firstOrFail();

            if (! $box->isAtKitchen($kitchenId)) {
                throw new \RuntimeException('This box is not in your kitchen inventory.');
            }

            if ($box->orderMiddoBoxes()->exists()) {
                throw new \RuntimeException('This box is reserved for a dispatched order.');
            }

            if ($damagedReturn) {
                if ($box->asset_status !== 'damaged') {
                    throw new \RuntimeException('Only damaged boxes can be sent on the damaged return path.');
                }
            } elseif ($box->asset_status === 'damaged') {
                throw new \RuntimeException('Use “Send damaged to Middo” for damaged boxes.');
            }

            if ($box->hasOpenWarehouseHandoff()) {
                throw new \RuntimeException('This box is already on a warehouse return run.');
            }

            KitchenWarehouseHandoff::query()
                ->where('middo_box_id', $box->id)
                ->whereIn('status', [
                    KitchenWarehouseHandoff::STATUS_RECEIVED,
                    KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS,
                ])
                ->delete();

            KitchenWarehouseHandoff::create([
                'middo_box_id' => $box->id,
                'kitchen_id' => $kitchenId,
                'rider_id' => null,
                'status' => KitchenWarehouseHandoff::STATUS_RUN_REQUESTED,
            ]);

            $baseNote = $damagedReturn
                ? 'Damaged box ready to ship to Middo warehouse — awaiting ops rider assignment'
                : 'Ready to ship to Middo warehouse — awaiting ops rider assignment';
            $extra = self::normalizeNotes($notes);
            $logNotes = $extra ? $baseNote.' — '.$extra : $baseNote;

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'assigned_at_kitchen',
                'log_action' => 'warehouse_run_requested',
                'notes' => $logNotes,
                'performed_by' => $kitchenId,
            ]);

            // Keep legacy log for older history filters.
            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'assigned_at_kitchen',
                'log_action' => 'staged_for_warehouse_pickup',
                'notes' => $logNotes,
                'performed_by' => $kitchenId,
            ]);

            return $box->fresh(['warehouseHandoff']);
        });

        $kitchen = User::query()->find($kitchenId);
        if ($kitchen) {
            StaffAlerts::notifyAreaRidersKitchenToOpsRunRequested($kitchen, [$fresh]);
        }

        return $fresh;
    }

    /**
     * @deprecated Ops assigns kitchen→ops riders via OpsAssignRider::kitchenToOps.
     */
    public static function claimWarehouseRun(int $boxId, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($boxId, $riderId) {
            $handoff = KitchenWarehouseHandoff::query()
                ->with(['kitchen', 'box'])
                ->where('middo_box_id', $boxId)
                ->where('status', KitchenWarehouseHandoff::STATUS_RUN_REQUESTED)
                ->whereNull('rider_id')
                ->lockForUpdate()
                ->first();

            if (! $handoff) {
                throw new \RuntimeException('This warehouse run is no longer available to claim.');
            }

            $rider = User::query()
                ->with('role')
                ->whereKey($riderId)
                ->where('status', 'active')
                ->whereHas('role', fn ($q) => $q->where('name', 'delivery'))
                ->first();
            if (! $rider) {
                throw new \RuntimeException('Only active delivery riders can claim this run.');
            }

            $kitchen = $handoff->kitchen ?? User::query()->find($handoff->kitchen_id);

            if (method_exists($rider, 'canAcceptNewRuns') && ! $rider->canAcceptNewRuns()) {
                throw new \RuntimeException('You are not on shift. Set On shift on the dashboard before accepting runs.');
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
                'log_action' => 'rider_claimed_warehouse_run',
                'notes' => $rider->name.' claimed warehouse return run',
                'performed_by' => $rider->id,
            ]);

            $fresh = $box->fresh(['warehouseHandoff.rider']);
            if ($kitchen) {
                StaffAlerts::notifyKitchenWarehouseRunClaimed($rider, $kitchen, [$fresh]);
            }

            return $fresh;
        });
    }

    /**
     * Kitchen dispatches the claimed box to the rider.
     */
    public static function dispatchWarehouseRun(int $boxId, int $kitchenId): MiddoBox
    {
        return DB::transaction(function () use ($boxId, $kitchenId) {
            $handoff = KitchenWarehouseHandoff::query()
                ->with(['rider', 'kitchen'])
                ->where('middo_box_id', $boxId)
                ->where('kitchen_id', $kitchenId)
                ->where('status', KitchenWarehouseHandoff::STATUS_RUN_CLAIMED)
                ->lockForUpdate()
                ->first();

            if (! $handoff || ! $handoff->rider_id) {
                throw new \RuntimeException('No rider has claimed this warehouse run yet.');
            }

            $box = MiddoBox::query()->whereKey($boxId)->lockForUpdate()->firstOrFail();
            if (! $box->isAtKitchen($kitchenId)) {
                throw new \RuntimeException('This box is not in your kitchen inventory.');
            }

            $handoff->update(['status' => KitchenWarehouseHandoff::STATUS_DISPATCHED]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'assigned_at_kitchen',
                'log_action' => 'kitchen_dispatched_warehouse_run',
                'notes' => 'Dispatched to '.($handoff->rider?->name ?? 'rider').' for Middo warehouse',
                'performed_by' => $kitchenId,
            ]);

            $fresh = $box->fresh(['warehouseHandoff.rider']);
            $kitchen = $handoff->kitchen ?? User::query()->find($kitchenId);
            $rider = $handoff->rider ?? User::query()->find($handoff->rider_id);
            if ($kitchen && $rider) {
                StaffAlerts::notifyRiderKitchenToOpsDispatched($rider, $kitchen, [$fresh]);
            }

            return $fresh;
        });
    }

    /**
     * Rider accepts the physical box at kitchen and starts the warehouse run.
     */
    public static function acceptWarehouseReturnCustody(int $boxId, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($boxId, $riderId) {
            $handoff = KitchenWarehouseHandoff::query()
                ->with(['kitchen', 'box'])
                ->where('middo_box_id', $boxId)
                ->where('rider_id', $riderId)
                ->where('status', KitchenWarehouseHandoff::STATUS_DISPATCHED)
                ->lockForUpdate()
                ->first();

            if (! $handoff) {
                throw new \RuntimeException('Kitchen has not dispatched this box to you yet.');
            }

            $box = MiddoBox::query()->whereKey($boxId)->lockForUpdate()->firstOrFail();
            if (! $box->isAtKitchen((int) $handoff->kitchen_id)) {
                throw new \RuntimeException('This box is not available at the kitchen for pickup.');
            }

            $rider = User::query()->findOrFail($riderId);

            $wasDamaged = $box->asset_status === 'damaged';

            $box->update([
                'kitchen_id' => null,
                'held_by_user_id' => $riderId,
                // Keep damaged flag through the rider leg so ops still sees a damaged return.
                'asset_status' => $wasDamaged ? 'damaged' : 'active',
                'last_scanned_at' => now(),
            ]);

            $handoff->update(['status' => KitchenWarehouseHandoff::STATUS_IN_TRANSIT]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'rider_accepted_warehouse_return',
                'notes' => $wasDamaged
                    ? 'Rider accepted damaged box — run started to Middo warehouse'
                    : 'Rider accepted empty box — run started to Middo warehouse',
                'performed_by' => $riderId,
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'dispatched_to_warehouse',
                'notes' => $wasDamaged ? 'Damaged box en route to Middo warehouse' : 'En route to Middo warehouse',
                'performed_by' => $riderId,
            ]);

            $perBox = RiderCommission::forSettingsRun($rider, DeliveryRunType::KITCHEN_TO_OPS);
            MiddoOperatingCosts::bookRiderCommission(
                $rider,
                DeliveryRunType::KITCHEN_TO_OPS,
                $perBox,
                MiddoBox::class,
                (int) $box->id,
                'Kitchen→ops box #'.($box->qr_code_id ?? $box->id),
                $rider->id
            );

            $fresh = $box->fresh();
            $kitchen = $handoff->kitchen ?? User::query()->find($handoff->kitchen_id);
            if ($kitchen) {
                StaffAlerts::notifyOpsKitchenToOpsInbound($rider, $kitchen, [$fresh]);
            }

            return $fresh;
        });
    }

    /**
     * Rider marks box handed to Middo ops — custody stays with the rider until ops confirms receive.
     */
    public static function handToOpsByRider(MiddoBox $box, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($box, $riderId) {
            $box = MiddoBox::query()->whereKey($box->id)->lockForUpdate()->firstOrFail();

            if ((int) $box->held_by_user_id !== $riderId) {
                throw new \RuntimeException('This box is not in your custody.');
            }

            $handoff = KitchenWarehouseHandoff::query()
                ->where('middo_box_id', $box->id)
                ->where('rider_id', $riderId)
                ->where('status', KitchenWarehouseHandoff::STATUS_IN_TRANSIT)
                ->lockForUpdate()
                ->first();

            if (! $handoff) {
                // Legacy path: latest log dispatched_to_warehouse.
                $latestAction = MiddoBoxLog::query()
                    ->where('middo_box_id', $box->id)
                    ->orderByDesc('id')
                    ->value('log_action');
                if ($latestAction !== 'dispatched_to_warehouse') {
                    throw new \RuntimeException('This box is not on an active kitchen→warehouse run.');
                }
            }

            $wasDamaged = $box->asset_status === 'damaged';

            // Keep rider custody — ops Confirm receive completes the transfer.
            $box->update([
                'kitchen_id' => null,
                'held_by_user_id' => $riderId,
                'asset_status' => $wasDamaged ? 'damaged' : 'active',
                'last_scanned_at' => now(),
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'handed_to_ops_warehouse',
                'notes' => $wasDamaged
                    ? 'Rider handed damaged box to Middo ops — awaiting ops confirm receive'
                    : 'Rider handed empty box to Middo ops — awaiting ops confirm receive',
                'performed_by' => $riderId,
            ]);

            if ($handoff) {
                $handoff->update(['status' => KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS]);
            } else {
                KitchenWarehouseHandoff::query()
                    ->where('middo_box_id', $box->id)
                    ->where('rider_id', $riderId)
                    ->where('status', KitchenWarehouseHandoff::STATUS_IN_TRANSIT)
                    ->update(['status' => KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS]);
            }

            $fresh = $box->fresh(['warehouseHandoff.kitchen', 'warehouseHandoff.rider']);
            $rider = User::query()->find($riderId);
            $kitchen = $handoff?->kitchen
                ?? User::query()->find($handoff?->kitchen_id)
                ?? ($fresh->warehouseHandoff?->kitchen);
            if ($rider && $kitchen) {
                StaffAlerts::notifyOpsKitchenToOpsReadyToReceive($rider, $kitchen, [$fresh]);
            }

            return $fresh;
        });
    }

    /**
     * @deprecated Use handToOpsByRider
     */
    public static function deliverToWarehouseByRider(MiddoBox $box, int $riderId): MiddoBox
    {
        return self::handToOpsByRider($box, $riderId);
    }

    /**
     * @deprecated Prefer markReadyToShip — kept for older call sites that tagged a rider directly.
     */
    public static function stageForWarehousePickup(MiddoBox $box, int $kitchenId, int $riderId): MiddoBox
    {
        $box = self::markReadyToShip($box, $kitchenId);
        self::claimWarehouseRun((int) $box->id, $riderId);

        return self::dispatchWarehouseRun((int) $box->id, $kitchenId);
    }

    /**
     * @deprecated Use stageForWarehousePickup / markReadyToShip
     */
    public static function dispatchToWarehouseViaRider(MiddoBox $box, int $kitchenId, int $riderId): MiddoBox
    {
        return self::stageForWarehousePickup($box, $kitchenId, $riderId);
    }

    /**
     * Damaged return entry point.
     * Via-rider on → same claim/dispatch/accept/hand lifecycle (asset stays damaged).
     * Via-rider off → teleport damaged to warehouse for ops review.
     */
    public static function sendDamagedToWarehouse(MiddoBox $box, int $kitchenId, ?string $notes = null): MiddoBox
    {
        if (MiddoSettings::kitchenToOpsViaRider()) {
            return self::markReadyToShip($box, $kitchenId, damagedReturn: true, notes: $notes);
        }

        return self::teleportDamagedToWarehouse($box, $kitchenId, $notes);
    }

    public static function teleportDamagedToWarehouse(MiddoBox $box, int $kitchenId, ?string $notes = null): MiddoBox
    {
        return DB::transaction(function () use ($box, $kitchenId, $notes) {
            $box = MiddoBox::query()->whereKey($box->id)->lockForUpdate()->firstOrFail();

            if (! $box->isAtKitchen($kitchenId)) {
                throw new \RuntimeException('This box is not in your kitchen inventory.');
            }

            if ($box->asset_status !== 'damaged') {
                throw new \RuntimeException('Only damaged boxes can be sent on the damaged return path.');
            }

            if ($box->orderMiddoBoxes()->exists()) {
                throw new \RuntimeException('This box is reserved for a dispatched order.');
            }

            if ($box->hasOpenWarehouseHandoff()) {
                throw new \RuntimeException('This box is already on a warehouse return run.');
            }

            $box->update([
                'kitchen_id' => null,
                'held_by_user_id' => null,
                'asset_status' => 'damaged',
                'last_scanned_at' => now(),
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'warehouse',
                'log_action' => 'returned_damaged_to_warehouse',
                'notes' => self::normalizeNotes($notes),
                'performed_by' => $kitchenId,
            ]);

            return $box->fresh();
        });
    }

    protected static function normalizeNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : mb_substr($notes, 0, 1000);
    }
}
