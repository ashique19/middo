<?php

namespace App\Support;

use App\Models\CustomRun;
use App\Models\OrderGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mutation helpers for the Operation Sanctum mobile API (mirrors Livewire ops flows).
 */
class OperationMobileActions
{
    public static function assignKitchen(OrderGroup $group, User $kitchen, User $actor): OrderGroup
    {
        $kitchen->loadMissing('role');
        if ($kitchen->role?->name !== 'kitchen' || $kitchen->status !== 'active') {
            throw new \RuntimeException('Pick an active kitchen user.');
        }

        $group->loadMissing(['kitchen', 'orders', 'menuItem']);

        $previousKitchenId = $group->kitchen_id;
        $nextKitchenId = (int) $kitchen->id;
        $fromKitchen = $group->kitchen?->name ?? 'Unassigned';
        $kitchenChanging = (int) ($previousKitchenId ?? 0) !== $nextKitchenId;

        if ($kitchenChanging) {
            $lockedStatuses = ['ready', 'packed', 'on_the_way_to_delivery', 'delivered', 'delivered_and_paid'];
            $hasLockedOrders = $group->orders->contains(function ($order) use ($lockedStatuses) {
                return in_array($order->order_status, $lockedStatuses, true)
                    || $order->dispatched_at !== null;
            });

            if ($hasLockedOrders) {
                throw new \RuntimeException('Cannot reassign kitchen after orders in this group are packed or dispatched.');
            }

            KitchenCapacity::assertCanAccept($kitchen);
        }

        $group->update([
            'kitchen_id' => $nextKitchenId,
            'updated_by' => $actor->id,
        ]);

        $group->refresh()->load(['kitchen', 'orders', 'menuItem']);
        $toKitchen = $group->kitchen?->name ?? 'Unassigned';

        if ((int) ($previousKitchenId ?? 0) !== (int) ($group->kitchen_id ?? 0)) {
            foreach ($group->orders as $order) {
                OrderAudit::record($order, 'forwarded_to_kitchen', [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'from_kitchen_id' => $previousKitchenId,
                    'to_kitchen_id' => $group->kitchen_id,
                    'from_kitchen' => $fromKitchen,
                    'to_kitchen' => $toKitchen,
                    'source' => $order->package_subscription_id ? 'package' : 'menu',
                    'via' => 'operation_mobile',
                ], $actor->id);
            }
        }

        if ($nextKitchenId && ! $previousKitchenId) {
            OrderKitchenAcceptance::markGroupOrdersProcessing($group, $actor->id);
        }

        if ($kitchenChanging) {
            StaffAlerts::notifyKitchenAssigned($group->fresh(['menuItem']), $kitchen);
        }

        return $group->fresh(['kitchen', 'orders', 'menuItem']);
    }

    /**
     * @param  list<int>  $groupIds
     * @return array{assigned:int, message:string}
     */
    public static function bulkAssignKitchen(array $groupIds, User $kitchen, User $actor): array
    {
        $kitchen->loadMissing('role');
        if ($kitchen->role?->name !== 'kitchen' || $kitchen->status !== 'active') {
            throw new \RuntimeException('Pick an active kitchen user.');
        }

        $assigned = 0;
        foreach ($groupIds as $groupId) {
            $group = OrderGroup::with('orders')->find($groupId);
            if (! $group || $group->kitchen_id !== null) {
                continue;
            }

            try {
                KitchenCapacity::assertCanAccept($kitchen);
            } catch (\RuntimeException $e) {
                return [
                    'assigned' => $assigned,
                    'message' => "Assigned {$assigned} group(s). Stopped: ".$e->getMessage(),
                ];
            }

            self::assignKitchen($group, $kitchen, $actor);
            $assigned++;
        }

        return [
            'assigned' => $assigned,
            'message' => $assigned > 0
                ? "Assigned {$assigned} group(s) to {$kitchen->name}."
                : 'No groups were assigned.',
        ];
    }

    /**
     * @param  array{from_label:string,to_label:string,rider_id:int,area_id?:int|null,commission_amount?:int|null,notes?:string|null}  $data
     */
    public static function createCustomRun(array $data, User $actor): CustomRun
    {
        $rider = User::query()->with('role')->findOrFail((int) $data['rider_id']);
        if ($rider->role?->name !== 'delivery') {
            throw new \RuntimeException('Assignee must be a delivery rider.');
        }

        $areaId = isset($data['area_id']) ? (int) $data['area_id'] : null;
        if ($areaId && ! $rider->servesArea($areaId)) {
            throw new \RuntimeException('Selected rider does not serve that area.');
        }

        $commission = array_key_exists('commission_amount', $data) && $data['commission_amount'] !== null
            ? (int) $data['commission_amount']
            : RiderCommission::forSettingsRun($rider, DeliveryRunType::CUSTOM);

        $run = CustomRun::create([
            'from_label' => trim((string) $data['from_label']),
            'to_label' => trim((string) $data['to_label']),
            'area_id' => $areaId,
            'rider_user_id' => $rider->id,
            'commission_amount' => $commission,
            'status' => CustomRun::STATUS_PENDING,
            'notes' => isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            'created_by' => $actor->id,
        ]);

        StaffAlerts::notifyRidersCustomRun($run);

        return $run->fresh(['rider', 'area']);
    }

    public static function cancelCustomRun(CustomRun $run, User $actor): CustomRun
    {
        return DB::transaction(function () use ($run, $actor) {
            $locked = CustomRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted() || $locked->isCancelled()) {
                throw new \RuntimeException('This custom run is already finished.');
            }

            if (! $locked->isPending() && ! $locked->isStarted()) {
                throw new \RuntimeException('Only pending or started runs can be cancelled.');
            }

            if ($locked->isStarted()) {
                MiddoOperatingCosts::voidRiderCommission(
                    DeliveryRunType::CUSTOM,
                    CustomRun::class,
                    (int) $locked->id,
                    (int) $actor->id,
                    'Ops cancelled started custom run #'.$locked->id
                );
            }

            $locked->update([
                'status' => CustomRun::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $locked->fresh(['rider', 'area']);
        });
    }
}
