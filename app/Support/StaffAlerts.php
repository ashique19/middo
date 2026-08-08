<?php

namespace App\Support;

use App\Models\CustomRun;
use App\Models\KitchenBoxRequest;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\Role;
use App\Models\StaffAlert;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StaffAlerts
{
    public static function notifyKitchenAssigned(OrderGroup $group, User $kitchen): ?StaffAlert
    {
        $group->loadMissing('menuItem');

        return self::createOnce(
            (int) $kitchen->id,
            StaffAlert::TYPE_GROUP_ASSIGNED,
            'Order group assigned',
            sprintf(
                '%s (%s) was assigned to your kitchen.',
                $group->name,
                $group->menuItem?->name ?? 'menu'
            ),
            (int) $group->id,
            ['source' => 'ops_assign'],
            'assigned:'.$group->id.':'.$kitchen->id.':'.now('Asia/Dhaka')->toDateString()
        );
    }

    /**
     * Kitchen asked ops for more Middo boxes.
     */
    public static function notifyOpsKitchenBoxRequest(KitchenBoxRequest $request): int
    {
        $request->loadMissing('kitchen');
        $kitchen = $request->kitchen;
        if (! $kitchen) {
            return 0;
        }

        $qty = (int) $request->quantity;
        $title = 'Box request: '.$kitchen->name;
        $body = sprintf(
            '%s requested %d Middo %s%s. Open Middo Boxes to stage pickup.',
            $kitchen->name,
            $qty,
            str('box')->plural($qty),
            $request->note ? ' — '.$request->note : ''
        );

        $created = 0;
        foreach (self::opsAndAdminUserIds() as $userId) {
            $alert = self::createOnce(
                $userId,
                StaffAlert::TYPE_KITCHEN_BOX_REQUEST,
                $title,
                $body,
                null,
                [
                    'kitchen_box_request_id' => $request->id,
                    'kitchen_id' => $kitchen->id,
                    'quantity' => $qty,
                ],
                'kitchen_box_request:'.$request->id.':'.$userId
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    public static function notifyOpsNeedsReassignment(
        OrderGroup $group,
        string $eventType,
        User $kitchen,
        ?string $reason = null
    ): int {
        $group->loadMissing('menuItem');

        $label = match ($eventType) {
            OrderGroupEvent::TYPE_SHORTAGE => 'Shortage',
            OrderGroupEvent::TYPE_DECLINE => 'Decline',
            OrderGroupEvent::TYPE_RELEASE => 'Release',
            default => ucfirst($eventType),
        };

        $title = "{$label}: {$group->name}";
        $body = sprintf(
            '%s reported by %s%s. Group is in the open pool for reassignment.',
            $label,
            $kitchen->name,
            $reason ? " — {$reason}" : ''
        );

        $dedupeBase = 'reassign:'.$group->id.':'.$eventType.':'.$kitchen->id.':'.now()->timestamp;
        $created = 0;

        foreach (self::opsAndAdminUserIds() as $userId) {
            $alert = self::createOnce(
                $userId,
                StaffAlert::TYPE_NEEDS_REASSIGNMENT,
                $title,
                $body,
                (int) $group->id,
                [
                    'event_type' => $eventType,
                    'kitchen_id' => $kitchen->id,
                    'reason' => $reason,
                ],
                $dedupeBase.':'.$userId
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    public static function notifyKitchenAcceptWindowClosing(OrderGroup $group, User $kitchen): ?StaffAlert
    {
        $group->loadMissing('menuItem');
        $closeAt = KitchenAcceptWindow::windowCloseAt($group);

        return self::createOnce(
            (int) $kitchen->id,
            StaffAlert::TYPE_ACCEPT_WINDOW_CLOSING,
            'Accept window closing soon',
            sprintf(
                '%s (%s) closes at %s. Accept soon if you can take it.',
                $group->name,
                $group->menuItem?->name ?? 'menu',
                $closeAt->format('g:i A')
            ),
            (int) $group->id,
            [
                'close_at' => $closeAt->toIso8601String(),
            ],
            'accept_warn:'.$group->id.':'.$kitchen->id.':'.$closeAt->toDateString()
        );
    }

    /**
     * Parcel call: kitchen marked lunch order ready — notify riders serving that area to accept.
     */
    public static function notifyRidersLunchReady(Order $order): int
    {
        $order->loadMissing(['menuItem', 'orderGroup', 'area']);
        $areaId = DeliveryAreaScope::orderAreaId($order);
        $riders = DeliveryAreaScope::ridersForArea($areaId);
        if ($riders === []) {
            return 0;
        }

        $menu = $order->menuItem?->name ?? 'Order';
        $areaName = $order->area?->name ?? $order->orderGroup?->area?->name;
        $title = 'Ready for claim #'.$order->id;
        $body = sprintf(
            '%s is ready at kitchen (qty %d)%s. Accept the run, then pick up after kitchen packs.',
            $menu,
            (int) $order->quantity,
            $areaName ? ' · '.$areaName : ''
        );
        $groupId = $order->orderGroup?->id;
        $created = 0;

        foreach ($riders as $rider) {
            $alert = self::createOnce(
                (int) $rider->id,
                StaffAlert::TYPE_LUNCH_DISPATCH,
                $title,
                $body,
                $groupId ? (int) $groupId : null,
                [
                    'order_id' => $order->id,
                    'area_id' => $areaId,
                    'run_type' => DeliveryRunType::KITCHEN_TO_CORPORATE,
                    'phase' => 'ready_for_claim',
                ],
                'lunch_ready:'.$order->id.':'.$rider->id
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Kitchen confirmed / packed for the claimed rider — time to pick up.
     */
    public static function notifyRiderLunchPacked(Order $order): int
    {
        $order->loadMissing(['menuItem', 'deliveryRider', 'orderGroup']);
        $riderId = (int) ($order->delivery_rider_id ?? 0);
        if ($riderId < 1) {
            return 0;
        }

        $menu = $order->menuItem?->name ?? 'Order';
        $alert = self::createOnce(
            $riderId,
            StaffAlert::TYPE_LUNCH_DISPATCH,
            'Packed — pick up #'.$order->id,
            sprintf('%s is packed. Confirm pickup at the kitchen.', $menu),
            $order->orderGroup?->id ? (int) $order->orderGroup->id : null,
            [
                'order_id' => $order->id,
                'phase' => 'packed_for_pickup',
                'run_type' => DeliveryRunType::KITCHEN_TO_CORPORATE,
            ],
            'lunch_packed:'.$order->id.':'.$riderId
        );

        return $alert ? 1 : 0;
    }

    /**
     * Parcel call: kitchen dispatched a lunch order — notify riders serving that area.
     *
     * @deprecated Prefer notifyRidersLunchReady + notifyRiderLunchPacked
     * @return int number of alerts created
     */
    public static function notifyRidersLunchDispatch(Order $order): int
    {
        return self::notifyRidersLunchReady($order);
    }

    /**
     * Parcel call: ops created a custom point→point run.
     *
     * @return int number of alerts created
     */
    public static function notifyRidersCustomRun(CustomRun $run): int
    {
        $run->loadMissing(['rider', 'area']);
        $riders = [];

        if ($run->rider_user_id && $run->rider) {
            $riders = [$run->rider];
        } elseif ($run->area_id) {
            $riders = DeliveryAreaScope::ridersForArea((int) $run->area_id);
        } else {
            return 0;
        }

        $title = 'Custom run #'.$run->id;
        $body = $run->label().($run->commission_amount > 0 ? ' · ৳'.$run->commission_amount : '');
        $created = 0;

        foreach ($riders as $rider) {
            $alert = self::createOnce(
                (int) $rider->id,
                StaffAlert::TYPE_CUSTOM_RUN,
                $title,
                $body,
                null,
                [
                    'custom_run_id' => $run->id,
                    'area_id' => $run->area_id,
                    'run_type' => DeliveryRunType::CUSTOM,
                ],
                'custom_run:'.$run->id.':'.$rider->id
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Ops assigned warehouse Middo boxes to a rider bound for a kitchen.
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     * @return int number of alerts created (rider + kitchen)
     */
    /**
     * Ops staged warehouse stock for a rider → kitchen run.
     * Alerts the rider to pick up. Kitchen is notified later when the rider hands stock.
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyOpsToKitchenBoxes(User $rider, User $kitchen, Collection|array $boxes): int
    {
        return self::notifyRiderOpsToKitchenPickup($rider, $kitchen, $boxes);
    }

    /**
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyRiderOpsToKitchenPickup(User $rider, User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $boxIds = $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all();
        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');
        $dedupeBase = 'ops_kitchen:'.$rider->id.':'.$kitchen->id.':'.implode('-', $boxIds);

        $riderAlert = self::createOnce(
            (int) $rider->id,
            StaffAlert::TYPE_OPS_TO_KITCHEN_BOX,
            $count === 1 ? 'Ops→kitchen box run' : "Ops→kitchen box run ({$count})",
            sprintf(
                'Pick up %s at warehouse, then deliver to %s.',
                $boxList,
                $kitchen->name
            ),
            null,
            [
                'box_ids' => $boxIds,
                'kitchen_id' => $kitchen->id,
                'run_type' => DeliveryRunType::OPS_TO_KITCHEN,
            ],
            $dedupeBase.':rider'
        );

        return $riderAlert ? 1 : 0;
    }

    /**
     * Rider handed warehouse stock at the kitchen — ready for kitchen confirm receive.
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyKitchenOpsToKitchenHanded(User $rider, User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $boxIds = $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all();
        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');
        $dedupeBase = 'ops_kitchen_handed:'.$rider->id.':'.$kitchen->id.':'.implode('-', $boxIds);

        $kitchenAlert = self::createOnce(
            (int) $kitchen->id,
            StaffAlert::TYPE_OPS_TO_KITCHEN_BOX,
            $count === 1 ? 'Incoming Middo box' : "Incoming Middo boxes ({$count})",
            sprintf(
                '%s handed %s at your kitchen — confirm receive on Incoming.',
                $rider->name,
                $boxList
            ),
            null,
            [
                'box_ids' => $boxIds,
                'rider_id' => $rider->id,
                'run_type' => DeliveryRunType::OPS_TO_KITCHEN,
                'phase' => 'handed',
            ],
            $dedupeBase.':kitchen'
        );

        return $kitchenAlert ? 1 : 0;
    }

    /**
     * Kitchen marked empty boxes ready to ship — notify area riders to claim the run.
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyAreaRidersKitchenToOpsRunRequested(User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $areaId = $kitchen->area_id !== null ? (int) $kitchen->area_id : null;
        $riders = DeliveryAreaScope::ridersForArea($areaId);
        if ($riders === []) {
            return 0;
        }

        $boxIds = $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all();
        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');
        $created = 0;

        foreach ($riders as $rider) {
            $alert = self::createOnce(
                (int) $rider->id,
                StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
                $count === 1 ? 'Kitchen→ops run requested' : "Kitchen→ops runs requested ({$count})",
                sprintf(
                    '%s ready to ship from %s — claim the run, then wait for kitchen dispatch.',
                    $boxList,
                    $kitchen->name
                ),
                null,
                [
                    'box_ids' => $boxIds,
                    'kitchen_id' => $kitchen->id,
                    'area_id' => $areaId,
                    'run_type' => DeliveryRunType::KITCHEN_TO_OPS,
                    'phase' => 'run_requested',
                ],
                'kitchen_ops_request:'.implode('-', $boxIds).':'.$rider->id
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyKitchenWarehouseRunClaimed(User $rider, User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $boxIds = $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all();
        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');

        $alert = self::createOnce(
            (int) $kitchen->id,
            StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
            $count === 1 ? 'Rider claimed warehouse run' : "Rider claimed warehouse runs ({$count})",
            sprintf('%s claimed %s — dispatch when ready.', $rider->name, $boxList),
            null,
            [
                'box_ids' => $boxIds,
                'rider_id' => $rider->id,
                'kitchen_id' => $kitchen->id,
                'run_type' => DeliveryRunType::KITCHEN_TO_OPS,
                'phase' => 'run_claimed',
            ],
            'kitchen_ops_claimed:'.implode('-', $boxIds).':'.$kitchen->id
        );

        return $alert ? 1 : 0;
    }

    /**
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyRiderKitchenToOpsDispatched(User $rider, User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $boxIds = $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all();
        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');

        $alert = self::createOnce(
            (int) $rider->id,
            StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
            $count === 1 ? 'Kitchen dispatched warehouse box' : "Kitchen dispatched warehouse boxes ({$count})",
            sprintf(
                '%s dispatched %s — accept the box at kitchen, then hand to Middo ops.',
                $kitchen->name,
                $boxList
            ),
            null,
            [
                'box_ids' => $boxIds,
                'kitchen_id' => $kitchen->id,
                'run_type' => DeliveryRunType::KITCHEN_TO_OPS,
                'phase' => 'dispatched',
            ],
            'kitchen_ops_dispatched:'.implode('-', $boxIds).':'.$rider->id
        );

        return $alert ? 1 : 0;
    }

    /**
     * @deprecated Prefer notifyAreaRidersKitchenToOpsRunRequested / notifyRiderKitchenToOpsDispatched
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyKitchenToOpsBoxes(User $rider, User $kitchen, Collection|array $boxes): int
    {
        return self::notifyRiderKitchenToOpsDispatched($rider, $kitchen, $boxes);
    }

    /**
     * @deprecated Prefer notifyRiderKitchenToOpsDispatched
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyRiderKitchenToOpsPickup(User $rider, User $kitchen, Collection|array $boxes): int
    {
        return self::notifyRiderKitchenToOpsDispatched($rider, $kitchen, $boxes);
    }

    /**
     * Rider accepted kitchen→ops empty-box custody — ops can expect inbound warehouse returns.
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyOpsKitchenToOpsInbound(User $rider, User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $boxIds = $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all();
        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');
        $dedupeBase = 'kitchen_ops_inbound:'.$rider->id.':'.$kitchen->id.':'.implode('-', $boxIds);
        $created = 0;

        foreach (self::opsAndAdminUserIds() as $userId) {
            $alert = self::createOnce(
                $userId,
                StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
                $count === 1 ? 'Inbound empty box (rider)' : "Inbound empty boxes ({$count})",
                sprintf('%s is returning %s from %s.', $rider->name, $boxList, $kitchen->name),
                null,
                [
                    'box_ids' => $boxIds,
                    'rider_id' => $rider->id,
                    'kitchen_id' => $kitchen->id,
                    'run_type' => DeliveryRunType::KITCHEN_TO_OPS,
                    'phase' => 'accepted',
                ],
                $dedupeBase.':ops:'.$userId
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Corporate marked an empty Middo box ready for pickup — notify riders serving that area.
     *
     * @return int number of alerts created
     */
    public static function notifyRidersEmptyBoxPickup(MiddoBox $box): int
    {
        $box->loadMissing('heldByUser');
        $holder = $box->heldByUser;
        $areaId = $holder?->area_id !== null ? (int) $holder->area_id : null;
        $riders = DeliveryAreaScope::ridersForArea($areaId);
        if ($riders === []) {
            return 0;
        }

        $qr = $box->qr_code_id ?: '#'.$box->id;
        $place = $holder?->name ?? 'corporate';
        $title = 'Empty box pickup '.$qr;
        $body = sprintf('%s is ready for pickup at %s.', $qr, $place);
        $created = 0;

        foreach ($riders as $rider) {
            $alert = self::createOnce(
                (int) $rider->id,
                StaffAlert::TYPE_EMPTY_BOX_PICKUP,
                $title,
                $body,
                null,
                [
                    'box_id' => $box->id,
                    'area_id' => $areaId,
                    'corporate_user_id' => $holder?->id,
                    'run_type' => DeliveryRunType::CORPORATE_TO_KITCHEN,
                ],
                'empty_box_pickup:'.$box->id.':'.$rider->id
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    public static function unreadCount(int $userId): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        return StaffAlert::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public static function markRead(int $alertId, int $userId): bool
    {
        $alert = StaffAlert::query()
            ->whereKey($alertId)
            ->where('user_id', $userId)
            ->first();

        if (! $alert) {
            return false;
        }

        $alert->markRead();

        return true;
    }

    public static function markAllRead(int $userId): int
    {
        return StaffAlert::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return list<int>
     */
    public static function opsAndAdminUserIds(): array
    {
        $roleIds = Role::query()
            ->whereIn('name', ['admin', 'operation'])
            ->pluck('id')
            ->all();

        if ($roleIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('role_id', $roleIds)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected static function createOnce(
        int $userId,
        string $type,
        string $title,
        ?string $body,
        ?int $orderGroupId,
        ?array $meta,
        ?string $dedupeKey
    ): ?StaffAlert {
        if (! self::tableReady()) {
            return null;
        }

        if ($dedupeKey !== null) {
            $existing = StaffAlert::query()
                ->where('user_id', $userId)
                ->where('dedupe_key', $dedupeKey)
                ->first();
            if ($existing) {
                return null;
            }
        }

        try {
            return StaffAlert::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'order_group_id' => $orderGroupId,
                'meta' => $meta,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function tableReady(): bool
    {
        try {
            return Schema::hasTable('staff_alerts');
        } catch (\Throwable) {
            return false;
        }
    }
}
