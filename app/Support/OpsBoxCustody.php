<?php

namespace App\Support;

use App\Models\KitchenWarehouseHandoff;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OpsBoxCustody
{
    /**
     * @return array{
     *   warehouse:int,
     *   at_kitchen:int,
     *   to_kitchen:int,
     *   with_rider:int,
     *   damaged:int,
     *   returns:int,
     *   staged_pickup:int,
     *   needs_kitchen_to_ops_rider:int,
     *   needs_empty_box_rider:int
     * }
     */
    public static function summary(): array
    {
        if (! Schema::hasTable('middo_boxes')) {
            return [
                'warehouse' => 0,
                'at_kitchen' => 0,
                'to_kitchen' => 0,
                'with_rider' => 0,
                'damaged' => 0,
                'returns' => 0,
                'staged_pickup' => 0,
                'needs_kitchen_to_ops_rider' => 0,
                'needs_empty_box_rider' => 0,
            ];
        }

        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');
        $riderIds = $deliveryRoleId
            ? User::query()->where('role_id', $deliveryRoleId)->pluck('id')
            : collect();

        return [
            // Free stock only — boxes staged for rider pickup are counted under To kitchen.
            'warehouse' => self::warehouseFreeQuery()->count(),
            'at_kitchen' => MiddoBox::query()
                ->whereNotNull('kitchen_id')
                ->whereColumn('kitchen_id', 'held_by_user_id')
                ->where('asset_status', '!=', 'damaged')
                ->count(),
            'to_kitchen' => self::toKitchenQuery()->count(),
            'with_rider' => $riderIds->isEmpty()
                ? 0
                : MiddoBox::query()->whereIn('held_by_user_id', $riderIds)->count(),
            'damaged' => MiddoBox::query()->where('asset_status', 'damaged')->count(),
            'returns' => self::returnsQuery()->count(),
            'staged_pickup' => MiddoBox::query()->stagedForKitchenPickup()->count(),
            'needs_kitchen_to_ops_rider' => self::unassignedKitchenToOpsQuery()->count(),
            'needs_empty_box_rider' => self::unassignedEmptyPickupQuery()->count(),
        ];
    }

    /**
     * Free warehouse stock available to stage for a kitchen request.
     */
    public static function warehouseFreeQuery(): Builder
    {
        return MiddoBox::query()->availableForKitchenStaging();
    }

    /**
     * Staged-for-pickup at warehouse + boxes already destined/en route to a kitchen.
     */
    public static function toKitchenQuery(): Builder
    {
        return MiddoBox::query()
            ->where(function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->whereNotNull('kitchen_id')
                        ->where(function (Builder $held) {
                            $held->whereNull('held_by_user_id')
                                ->orWhereColumn('held_by_user_id', '!=', 'kitchen_id');
                        })
                        ->where('asset_status', '!=', 'retired');
                })->orWhere(function (Builder $inner) {
                    $inner->stagedForKitchenPickup();
                });
            });
    }

    /**
     * Boxes awaiting ops confirm receive:
     * - Via-rider: latest log handed_to_ops_warehouse (rider still holds custody)
     * - Direct teleport / legacy: latest log returned_*_to_warehouse
     */
    public static function returnsQuery(): Builder
    {
        $latestLogIds = MiddoBoxLog::query()
            ->select(DB::raw('MAX(id)'))
            ->groupBy('middo_box_id');

        $returnBoxIds = MiddoBoxLog::query()
            ->whereIn('id', $latestLogIds)
            ->whereIn('log_action', [
                'handed_to_ops_warehouse',
                'returned_to_warehouse',
                'returned_damaged_to_warehouse',
            ])
            ->pluck('middo_box_id');

        return MiddoBox::query()->whereIn('id', $returnBoxIds);
    }

    public static function unassignedKitchenToOpsQuery(): Builder
    {
        if (! Schema::hasTable('kitchen_warehouse_handoffs')) {
            return MiddoBox::query()->whereRaw('0 = 1');
        }

        $ids = KitchenWarehouseHandoff::query()
            ->where('status', KitchenWarehouseHandoff::STATUS_RUN_REQUESTED)
            ->whereNull('rider_id')
            ->pluck('middo_box_id');

        return MiddoBox::query()->whereIn('id', $ids);
    }

    public static function unassignedEmptyPickupQuery(): Builder
    {
        $query = MiddoBox::query()
            ->where('ready_for_pickup', true)
            ->whereHas('heldByUser.role', fn ($q) => $q->where('name', 'corporate'));

        if (Schema::hasColumn('middo_boxes', 'pickup_rider_id')) {
            $query->whereNull('pickup_rider_id');
        }

        return $query;
    }

    /**
     * Ops confirms receive — custody transfers to Middo warehouse inventory.
     */
    public static function ackReturn(MiddoBox $box, ?int $actorId = null): MiddoBox
    {
        return DB::transaction(function () use ($box, $actorId) {
            $box = MiddoBox::query()->whereKey($box->id)->lockForUpdate()->firstOrFail();

            $latestAction = MiddoBoxLog::query()
                ->where('middo_box_id', $box->id)
                ->orderByDesc('id')
                ->value('log_action');

            $awaitingHanded = $latestAction === 'handed_to_ops_warehouse';
            $awaitingReturned = in_array($latestAction, [
                'returned_to_warehouse',
                'returned_damaged_to_warehouse',
            ], true);

            if (! $awaitingHanded && ! $awaitingReturned) {
                throw new \RuntimeException('This box is not awaiting ops confirm receive.');
            }

            $wasDamaged = $box->asset_status === 'damaged'
                || $latestAction === 'returned_damaged_to_warehouse';

            // Custody transfer into Middo warehouse (confirm receive).
            $box->update([
                'asset_status' => 'at_middo_warehouse',
                'kitchen_id' => null,
                'held_by_user_id' => null,
                'last_scanned_at' => now(),
            ]);

            if ($awaitingHanded) {
                MiddoBoxLog::create([
                    'middo_box_id' => $box->id,
                    'custody_status' => 'warehouse',
                    'log_action' => $wasDamaged ? 'returned_damaged_to_warehouse' : 'returned_to_warehouse',
                    'notes' => 'Ops confirmed receive — custody transferred to Middo warehouse',
                    'performed_by' => $actorId,
                ]);
            }

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'warehouse',
                'log_action' => 'ops_acked_warehouse_return',
                'notes' => 'Ops confirmed receive — custody at Middo warehouse',
                'performed_by' => $actorId,
            ]);

            if (Schema::hasTable('kitchen_warehouse_handoffs')) {
                KitchenWarehouseHandoff::query()
                    ->where('middo_box_id', $box->id)
                    ->whereIn('status', [
                        KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS,
                        KitchenWarehouseHandoff::STATUS_IN_TRANSIT,
                    ])
                    ->update(['status' => KitchenWarehouseHandoff::STATUS_RECEIVED]);
            }

            return $box->fresh();
        });
    }
}
