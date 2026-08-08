<?php

namespace App\Support;

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
     *   staged_pickup:int
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
     * Boxes whose latest log is a kitchen→warehouse return (ops inbound review queue).
     */
    public static function returnsQuery(): Builder
    {
        $latestLogIds = MiddoBoxLog::query()
            ->select(DB::raw('MAX(id)'))
            ->groupBy('middo_box_id');

        $returnBoxIds = MiddoBoxLog::query()
            ->whereIn('id', $latestLogIds)
            ->whereIn('log_action', ['returned_to_warehouse', 'returned_damaged_to_warehouse'])
            ->pluck('middo_box_id');

        return MiddoBox::query()->whereIn('id', $returnBoxIds);
    }

    /**
     * Ack a returned box into warehouse inventory (clear damage when needed).
     */
    public static function ackReturn(MiddoBox $box, ?int $actorId = null): MiddoBox
    {
        $box->update([
            'asset_status' => 'at_middo_warehouse',
            'kitchen_id' => null,
            'held_by_user_id' => null,
            'last_scanned_at' => now(),
        ]);

        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'warehouse',
            'log_action' => 'ops_acked_warehouse_return',
            'notes' => 'Ops acknowledged inbound return',
            'performed_by' => $actorId,
        ]);

        return $box->fresh();
    }
}
