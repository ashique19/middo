<?php

namespace App\Support;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
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
