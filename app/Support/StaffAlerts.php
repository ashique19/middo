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
     * Kitchen accepted the menu group (or marked ready) — ops can assign a rider
     * before kitchen marks ready.
     */
    public static function notifyOpsLunchNeedsRider(Order $order): int
    {
        $order->loadMissing(['menuItem', 'orderGroup', 'area']);
        $menu = $order->menuItem?->name ?? 'Order';
        $areaName = $order->area?->name ?? $order->orderGroup?->area?->name;
        $title = 'Assign lunch rider #'.$order->id;
        $body = sprintf(
            '%s — kitchen accepted (qty %d)%s. Assign a rider on Rider ops (before or after they mark ready).',
            $menu,
            (int) $order->quantity,
            $areaName ? ' · '.$areaName : ''
        );
        $created = 0;

        foreach (self::opsAndAdminUserIds() as $userId) {
            $alert = self::createOnce(
                $userId,
                StaffAlert::TYPE_LUNCH_DISPATCH,
                $title,
                $body,
                $order->orderGroup?->id ? (int) $order->orderGroup->id : null,
                [
                    'order_id' => $order->id,
                    'phase' => 'needs_ops_assign',
                    'run_type' => DeliveryRunType::KITCHEN_TO_CORPORATE,
                ],
                'lunch_ops_assign:'.$order->id.':'.$userId
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @deprecated Ops assigns lunch riders; kept as an alias for older call sites.
     */
    public static function notifyRidersLunchReady(Order $order): int
    {
        return self::notifyOpsLunchNeedsRider($order);
    }

    /**
     * Ops assigned a lunch run — rider waits for kitchen pack (or picks up if already packed).
     */
    public static function notifyRiderLunchAssigned(Order $order): int
    {
        $order->loadMissing(['menuItem', 'deliveryRider', 'orderGroup']);
        $riderId = (int) ($order->delivery_rider_id ?? 0);
        if ($riderId < 1) {
            return 0;
        }

        $menu = $order->menuItem?->name ?? 'Order';
        $packed = $order->order_status === OrderTransition::PACKED;
        $prepping = $order->order_status === OrderTransition::PROCESSING;
        $title = $packed
            ? 'Assigned — pick up #'.$order->id
            : 'Assigned lunch #'.$order->id;
        $body = $packed
            ? sprintf('%s is packed. Confirm pickup at the kitchen.', $menu)
            : ($prepping
                ? sprintf('%s assigned to you. Kitchen is still prepping — wait, then pick up.', $menu)
                : sprintf('%s assigned to you. Wait for kitchen to pack, then pick up.', $menu));
        $alert = self::createOnce(
            $riderId,
            StaffAlert::TYPE_LUNCH_DISPATCH,
            $title,
            $body,
            $order->orderGroup?->id ? (int) $order->orderGroup->id : null,
            [
                'order_id' => $order->id,
                'phase' => $packed ? 'assigned_packed' : 'assigned_awaiting_pack',
                'run_type' => DeliveryRunType::KITCHEN_TO_CORPORATE,
            ],
            'lunch_assigned:'.$order->id.':'.$riderId
        );

        return $alert ? 1 : 0;
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
     *
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
     * Kitchen marked empty boxes ready to ship — ops assigns a rider.
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyOpsKitchenToOpsNeedsRider(User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $boxIds = $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all();
        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');
        $created = 0;

        foreach (self::opsAndAdminUserIds() as $userId) {
            $alert = self::createOnce(
                $userId,
                StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
                $count === 1 ? 'Assign kitchen→ops rider' : "Assign kitchen→ops riders ({$count})",
                sprintf('%s ready to ship from %s — assign a rider on Middo Boxes.', $boxList, $kitchen->name),
                null,
                [
                    'box_ids' => $boxIds,
                    'kitchen_id' => $kitchen->id,
                    'run_type' => DeliveryRunType::KITCHEN_TO_OPS,
                    'phase' => 'needs_ops_assign',
                ],
                'kitchen_ops_ops_assign:'.implode('-', $boxIds).':'.$userId
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @deprecated Ops assigns kitchen→ops riders.
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyAreaRidersKitchenToOpsRunRequested(User $kitchen, Collection|array $boxes): int
    {
        return self::notifyOpsKitchenToOpsNeedsRider($kitchen, $boxes);
    }

    /**
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyRiderKitchenToOpsAssigned(User $rider, ?User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');
        $kitchenName = $kitchen?->name ?? 'kitchen';

        $alert = self::createOnce(
            (int) $rider->id,
            StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
            $count === 1 ? 'Assigned kitchen→ops run' : "Assigned kitchen→ops runs ({$count})",
            sprintf('%s from %s — wait for kitchen dispatch, then accept the box.', $boxList, $kitchenName),
            null,
            [
                'box_ids' => $boxes->map(fn (MiddoBox $b) => (int) $b->id)->all(),
                'kitchen_id' => $kitchen?->id,
                'run_type' => DeliveryRunType::KITCHEN_TO_OPS,
                'phase' => 'ops_assigned',
            ],
            'kitchen_ops_assigned:'.implode('-', $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all()).':'.$rider->id
        );

        return $alert ? 1 : 0;
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
            $count === 1 ? 'Rider assigned warehouse run' : "Rider assigned warehouse runs ({$count})",
            sprintf('%s assigned to %s — dispatch when ready.', $rider->name, $boxList),
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
     * Rider handed kitchen→ops box at Middo — ops must Confirm receive to take custody.
     *
     * @param  Collection<int, MiddoBox>|list<MiddoBox>  $boxes
     */
    public static function notifyOpsKitchenToOpsReadyToReceive(User $rider, User $kitchen, Collection|array $boxes): int
    {
        $boxes = collect($boxes)->filter()->values();
        if ($boxes->isEmpty()) {
            return 0;
        }

        $boxIds = $boxes->map(fn (MiddoBox $b) => (int) $b->id)->sort()->values()->all();
        $labels = $boxes->map(fn (MiddoBox $b) => $b->qr_code_id ?: '#'.$b->id)->all();
        $count = $boxes->count();
        $boxList = implode(', ', array_slice($labels, 0, 5)).($count > 5 ? '…' : '');
        $dedupeBase = 'kitchen_ops_receive:'.$rider->id.':'.$kitchen->id.':'.implode('-', $boxIds);
        $created = 0;

        foreach (self::opsAndAdminUserIds() as $userId) {
            $alert = self::createOnce(
                $userId,
                StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
                $count === 1 ? 'Confirm receive (kitchen→ops)' : "Confirm receive ({$count} boxes)",
                sprintf(
                    '%s handed %s from %s — confirm receive on Middo Boxes to take custody.',
                    $rider->name,
                    $boxList,
                    $kitchen->name
                ),
                null,
                [
                    'box_ids' => $boxIds,
                    'rider_id' => $rider->id,
                    'kitchen_id' => $kitchen->id,
                    'run_type' => DeliveryRunType::KITCHEN_TO_OPS,
                    'phase' => 'handed_awaiting_receive',
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
     * Corporate marked an empty Middo box ready — ops assigns a collect rider.
     */
    public static function notifyOpsEmptyBoxNeedsRider(MiddoBox $box): int
    {
        $box->loadMissing('heldByUser');
        $holder = $box->heldByUser;
        $qr = $box->qr_code_id ?: '#'.$box->id;
        $place = $holder?->name ?? 'corporate';
        $created = 0;

        foreach (self::opsAndAdminUserIds() as $userId) {
            $alert = self::createOnce(
                $userId,
                StaffAlert::TYPE_EMPTY_BOX_PICKUP,
                'Assign empty-box rider '.$qr,
                sprintf('%s is ready for pickup at %s. Assign a rider on Middo Boxes.', $qr, $place),
                null,
                [
                    'box_id' => $box->id,
                    'corporate_user_id' => $holder?->id,
                    'run_type' => DeliveryRunType::CORPORATE_TO_KITCHEN,
                    'phase' => 'needs_ops_assign',
                ],
                'empty_box_ops_assign:'.$box->id.':'.$userId
            );
            if ($alert) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @deprecated Ops assigns empty-box collect riders.
     */
    public static function notifyRidersEmptyBoxPickup(MiddoBox $box): int
    {
        return self::notifyOpsEmptyBoxNeedsRider($box);
    }

    public static function notifyRiderEmptyBoxAssigned(MiddoBox $box, User $rider): int
    {
        $box->loadMissing('heldByUser');
        $qr = $box->qr_code_id ?: '#'.$box->id;
        $place = $box->heldByUser?->name ?? 'corporate';

        $alert = self::createOnce(
            (int) $rider->id,
            StaffAlert::TYPE_EMPTY_BOX_PICKUP,
            'Assigned empty box pickup '.$qr,
            sprintf('Collect %s at %s, then hand to kitchen.', $qr, $place),
            null,
            [
                'box_id' => $box->id,
                'corporate_user_id' => $box->heldByUser?->id,
                'run_type' => DeliveryRunType::CORPORATE_TO_KITCHEN,
                'phase' => 'ops_assigned',
            ],
            'empty_box_assigned:'.$box->id.':'.$rider->id
        );

        return $alert ? 1 : 0;
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
