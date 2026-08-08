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
                throw new \RuntimeException('Cancel the rider pickup tag before marking this box damaged.');
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

    public static function sendToWarehouse(MiddoBox $box, int $kitchenId): MiddoBox
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
                throw new \RuntimeException('This box is already tagged for a rider. Wait for pickup or keep it on the rider path.');
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
     * Kitchen tags a rider for empty-box return. Box stays at kitchen until the rider accepts.
     * Caller must enforce MiddoSettings::kitchenToOpsViaRider().
     */
    public static function stageForWarehousePickup(MiddoBox $box, int $kitchenId, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($box, $kitchenId, $riderId) {
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

            if ($box->asset_status === 'damaged') {
                throw new \RuntimeException('Use “Send damaged to Middo” for damaged boxes.');
            }

            if ($box->hasOpenWarehouseHandoff()) {
                throw new \RuntimeException('This box is already tagged for rider pickup.');
            }

            $rider = self::assertActiveDeliveryRider($riderId, $kitchenId);
            $kitchen = User::query()->findOrFail($kitchenId);

            // Unique middo_box_id: clear completed handoffs so stock can return again later.
            KitchenWarehouseHandoff::query()
                ->where('middo_box_id', $box->id)
                ->where('status', KitchenWarehouseHandoff::STATUS_DELIVERED)
                ->delete();

            KitchenWarehouseHandoff::create([
                'middo_box_id' => $box->id,
                'kitchen_id' => $kitchenId,
                'rider_id' => $rider->id,
                'status' => KitchenWarehouseHandoff::STATUS_READY_FOR_PICKUP,
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'assigned_at_kitchen',
                'log_action' => 'staged_for_warehouse_pickup',
                'notes' => 'Ready for rider pickup by '.$rider->name.' → Middo warehouse',
                'performed_by' => $kitchenId,
            ]);

            $fresh = $box->fresh(['warehouseHandoff.rider']);
            StaffAlerts::notifyKitchenToOpsBoxes($rider, $kitchen, [$fresh]);

            return $fresh;
        });
    }

    /**
     * @deprecated Use stageForWarehousePickup — kept as alias for older call sites/tests.
     */
    public static function dispatchToWarehouseViaRider(MiddoBox $box, int $kitchenId, int $riderId): MiddoBox
    {
        return self::stageForWarehousePickup($box, $kitchenId, $riderId);
    }

    /**
     * Rider accepts kitchen→ops empty-box custody at the kitchen.
     */
    public static function acceptWarehouseReturnCustody(int $boxId, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($boxId, $riderId) {
            $handoff = KitchenWarehouseHandoff::query()
                ->with(['kitchen', 'box'])
                ->where('middo_box_id', $boxId)
                ->where('rider_id', $riderId)
                ->where('status', KitchenWarehouseHandoff::STATUS_READY_FOR_PICKUP)
                ->lockForUpdate()
                ->first();

            if (! $handoff) {
                throw new \RuntimeException('This box is not staged for your kitchen→warehouse pickup.');
            }

            $box = MiddoBox::query()->whereKey($boxId)->lockForUpdate()->firstOrFail();
            if (! $box->isAtKitchen((int) $handoff->kitchen_id)) {
                throw new \RuntimeException('This box is not available at the kitchen for pickup.');
            }

            $rider = User::query()->findOrFail($riderId);

            $box->update([
                'kitchen_id' => null,
                'held_by_user_id' => $riderId,
                'asset_status' => 'active',
                'last_scanned_at' => now(),
            ]);

            $handoff->update(['status' => KitchenWarehouseHandoff::STATUS_RIDER_ACCEPTED]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'rider_accepted_warehouse_return',
                'notes' => 'Rider accepted empty box for Middo warehouse return',
                'performed_by' => $riderId,
            ]);

            // Keep legacy action so deliverToWarehouseByRider / UI gates stay compatible.
            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'dispatched_to_warehouse',
                'notes' => 'En route to Middo warehouse',
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
     * Rider completes kitchen→ops leg: box arrives at Middo warehouse (ops ack queue).
     */
    public static function deliverToWarehouseByRider(MiddoBox $box, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($box, $riderId) {
            $box = MiddoBox::query()->whereKey($box->id)->lockForUpdate()->firstOrFail();

            if ((int) $box->held_by_user_id !== $riderId) {
                throw new \RuntimeException('This box is not in your custody.');
            }

            if ($box->asset_status !== 'active') {
                throw new \RuntimeException('This box cannot be delivered to the warehouse.');
            }

            $latestAction = MiddoBoxLog::query()
                ->where('middo_box_id', $box->id)
                ->orderByDesc('id')
                ->value('log_action');

            if ($latestAction !== 'dispatched_to_warehouse') {
                throw new \RuntimeException('This box is not on a kitchen→warehouse run.');
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
                'performed_by' => $riderId,
            ]);

            KitchenWarehouseHandoff::query()
                ->where('middo_box_id', $box->id)
                ->where('rider_id', $riderId)
                ->where('status', KitchenWarehouseHandoff::STATUS_RIDER_ACCEPTED)
                ->update(['status' => KitchenWarehouseHandoff::STATUS_DELIVERED]);

            return $box->fresh();
        });
    }

    public static function sendDamagedToWarehouse(MiddoBox $box, int $kitchenId, ?string $notes = null): MiddoBox
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

    protected static function assertActiveDeliveryRider(int $riderId, int $kitchenId): User
    {
        $rider = User::query()
            ->with(['role', 'areas'])
            ->whereKey($riderId)
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('name', 'delivery'))
            ->first();

        if (! $rider) {
            throw new \RuntimeException('Selected rider is not available.');
        }

        // Area is a preference for listing order only — kitchen→ops empty-box runs
        // may use any active delivery rider (same as ops→kitchen staging).
        return $rider;
    }

    protected static function normalizeNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : mb_substr($notes, 0, 1000);
    }
}
