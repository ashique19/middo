<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderGroupKitchenAssignment
{
    /**
     * Kitchen self-accept of an open pool group.
     */
    public static function accept(OrderGroup $group, User $kitchen): OrderGroup
    {
        return DB::transaction(function () use ($group, $kitchen) {
            $kitchen = User::query()->whereKey($kitchen->id)->lockForUpdate()->firstOrFail();
            $group = OrderGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();

            if ($group->kitchen_id !== null) {
                throw new \RuntimeException('This order group was already accepted by another kitchen.');
            }

            KitchenCapacity::assertCanAccept($kitchen);
            KitchenAcceptWindow::assertCanAccept($group);

            $group->update([
                'kitchen_id' => $kitchen->id,
                'updated_by' => $kitchen->id,
            ]);

            OrderKitchenAcceptance::markGroupOrdersProcessing($group, $kitchen->id);

            OrderGroupEvent::create([
                'order_group_id' => $group->id,
                'kitchen_id' => $kitchen->id,
                'type' => OrderGroupEvent::TYPE_ACCEPT,
                'reason' => null,
                'meta' => null,
                'created_by' => $kitchen->id,
            ]);

            return $group->fresh();
        });
    }

    /**
     * Decline an unassigned pool group (does not assign). Hides it from this kitchen for the day.
     */
    public static function decline(OrderGroup $group, User $kitchen, string $reason, ?array $meta = null): OrderGroupEvent
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A decline reason is required.');
        }

        return DB::transaction(function () use ($group, $kitchen, $reason, $meta) {
            $group = OrderGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();

            if ($group->kitchen_id !== null) {
                throw new \RuntimeException('This order group is already assigned. Use release or shortage instead.');
            }

            $event = OrderGroupEvent::create([
                'order_group_id' => $group->id,
                'kitchen_id' => $kitchen->id,
                'type' => OrderGroupEvent::TYPE_DECLINE,
                'reason' => $reason,
                'meta' => $meta,
                'created_by' => $kitchen->id,
            ]);

            StaffAlerts::notifyOpsNeedsReassignment($group, OrderGroupEvent::TYPE_DECLINE, $kitchen, $reason);

            return $event;
        });
    }

    /**
     * Release an assigned group back to the open pool (processing only).
     */
    public static function release(OrderGroup $group, User $kitchen, string $type = OrderGroupEvent::TYPE_RELEASE, ?string $reason = null, ?array $meta = null): OrderGroup
    {
        return DB::transaction(function () use ($group, $kitchen, $type, $reason, $meta) {
            $group = OrderGroup::query()
                ->with('orders')
                ->whereKey($group->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $group->kitchen_id !== (int) $kitchen->id) {
                throw new \RuntimeException('This order group is not assigned to your kitchen.');
            }

            self::assertCanRelease($group);

            $orderIds = $group->orders->pluck('id')->all();

            if ($orderIds !== []) {
                Order::query()
                    ->whereIn('id', $orderIds)
                    ->where('order_status', OrderTransition::PROCESSING)
                    ->whereNull('dispatched_at')
                    ->update([
                        'order_status' => 'pending',
                        'updated_by' => $kitchen->id,
                    ]);
            }

            $group->update([
                'kitchen_id' => null,
                'updated_by' => $kitchen->id,
            ]);

            OrderGroupEvent::create([
                'order_group_id' => $group->id,
                'kitchen_id' => $kitchen->id,
                'type' => $type,
                'reason' => $reason,
                'meta' => $meta,
                'created_by' => $kitchen->id,
            ]);

            StaffAlerts::notifyOpsNeedsReassignment($group->fresh(), $type, $kitchen, $reason);

            return $group->fresh();
        });
    }

    /**
     * Report shortage on an assigned group, then release it back to the pool.
     */
    public static function reportShortage(OrderGroup $group, User $kitchen, string $reason, ?array $meta = null): OrderGroup
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A shortage reason is required.');
        }

        return self::release(
            $group,
            $kitchen,
            OrderGroupEvent::TYPE_SHORTAGE,
            $reason,
            $meta
        );
    }

    public static function assertCanRelease(OrderGroup $group): void
    {
        $group->loadMissing('orders');

        $blocked = $group->orders->contains(function (Order $order) {
            if ($order->order_status === 'cancelled') {
                return false;
            }

            if ($order->dispatched_at !== null) {
                return true;
            }

            return in_array($order->order_status, [
                OrderTransition::READY,
                OrderTransition::PACKED,
                OrderTransition::ON_THE_WAY_TO_DELIVERY,
                OrderTransition::DELIVERED,
                OrderTransition::DELIVERED_AND_PAID,
            ], true);
        });

        if ($blocked) {
            throw new \RuntimeException(
                'Cannot release this group after any order is marked ready, packed, or dispatched.'
            );
        }
    }

    public static function canRelease(OrderGroup $group): bool
    {
        try {
            self::assertCanRelease($group);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Groups this kitchen declined today (Asia/Dhaka) — hidden from their pool list.
     *
     * @return list<int>
     */
    public static function declinedGroupIdsForKitchenToday(int $kitchenId): array
    {
        $dayStart = now('Asia/Dhaka')->startOfDay()->timezone('UTC');
        $dayEnd = now('Asia/Dhaka')->endOfDay()->timezone('UTC');

        return OrderGroupEvent::query()
            ->where('kitchen_id', $kitchenId)
            ->where('type', OrderGroupEvent::TYPE_DECLINE)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->pluck('order_group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
