<?php

namespace App\Support;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
     * Kitchen→ops rider leg: assign empty box to rider bound for Middo warehouse.
     * Caller must enforce MiddoSettings::kitchenToOpsViaRider().
     */
    public static function dispatchToWarehouseViaRider(MiddoBox $box, int $kitchenId, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($box, $kitchenId, $riderId) {
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

            $rider = User::query()
                ->with(['role', 'areas'])
                ->whereKey($riderId)
                ->where('status', 'active')
                ->whereHas('role', fn ($q) => $q->where('name', 'delivery'))
                ->first();

            if (! $rider) {
                throw new \RuntimeException('Selected rider is not available.');
            }

            $kitchen = User::query()->find($kitchenId);
            $kitchenAreaId = $kitchen?->area_id !== null ? (int) $kitchen->area_id : null;
            if ($kitchenAreaId !== null && ! $rider->servesArea($kitchenAreaId)) {
                throw new \RuntimeException('Selected rider does not serve this kitchen’s area.');
            }

            $box->update([
                'kitchen_id' => null,
                'held_by_user_id' => $rider->id,
                'asset_status' => 'active',
                'last_scanned_at' => now(),
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'dispatched_to_warehouse',
                'performed_by' => $kitchenId,
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
            if ($kitchen) {
                StaffAlerts::notifyKitchenToOpsBoxes($rider, $kitchen, [$fresh]);
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

    protected static function normalizeNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : mb_substr($notes, 0, 1000);
    }
}
