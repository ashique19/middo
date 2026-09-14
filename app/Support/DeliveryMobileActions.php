<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

use App\Models\CashHandover;
use App\Models\CashHandoverOrder;
use App\Models\CustomRun;
use App\Models\KitchenBoxRequestBox;
use App\Models\KitchenWarehouseHandoff;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\RiderWithdrawalRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transaction helpers for Delivery Sanctum mobile API (extracted from Livewire).
 *
 * Never implements rider first-claim for lunch or kitchen→ops.
 */
class DeliveryMobileActions
{
    public static function pickUpOrder(int $orderId, int $riderId): Order
    {
        return DB::transaction(function () use ($orderId, $riderId) {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (! $order || ! $order->isAssignedToRider($riderId) || ! $order->isAwaitingRiderPickup()) {
                throw new \RuntimeException('This packed order is not ready for your pickup.');
            }

            $rider = User::query()->findOrFail($riderId);
            $boxes = $order->middoBoxes()->lockForUpdate()->get();

            if ($boxes->count() !== (int) $order->quantity) {
                throw new \RuntimeException('Reserved boxes for this order are incomplete.');
            }

            $kitchenId = DB::table('order_group_orders')
                ->join('order_groups', 'order_groups.id', '=', 'order_group_orders.order_group_id')
                ->where('order_group_orders.order_id', $order->id)
                ->value('order_groups.kitchen_id');

            foreach ($boxes as $box) {
                if ($kitchenId && ! $box->isAtKitchen((int) $kitchenId)) {
                    throw new \RuntimeException("{$box->qr_code_id} is not at the kitchen anymore.");
                }

                $box->update([
                    'held_by_user_id' => $riderId,
                    'kitchen_id' => null,
                    'asset_status' => 'active',
                    'total_uses_count' => $box->total_uses_count + 1,
                    'last_scanned_at' => now(),
                ]);

                MiddoBoxLog::create([
                    'order_id' => $order->id,
                    'middo_box_id' => $box->id,
                    'custody_status' => 'in_transit',
                    'log_action' => 'picked_by_delivery_from_kitchen',
                ]);
            }

            OrderTransition::apply($order, OrderTransition::ON_THE_WAY_TO_DELIVERY, [
                'updated_by' => $riderId,
            ]);

            OrderMoneyFlow::accrueDeliveryShareOnRunStart($order->fresh(['menuItem', 'orderGroup']), $rider);

            return $order->fresh([
                'menuItem', 'user', 'area', 'deliveryRider', 'orderGroup.kitchen', 'middoBoxes',
            ]);
        });
    }

    public static function deliverToConsumer(
        int $orderId,
        int $riderId,
        ?string $otp = null,
        ?string $podPhotoPath = null,
    ): Order {
        return DB::transaction(function () use ($orderId, $riderId, $otp, $podPhotoPath) {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (! $order || ! $order->isAssignedToRider($riderId)) {
                throw new \RuntimeException('You can only deliver orders you have accepted.');
            }

            if (! $order->isOnTheWayToDelivery()) {
                throw new \RuntimeException('This order is not on the way to delivery.');
            }

            if (DeliveryPodOtp::isRequired()) {
                if ($otp === null || trim($otp) === '') {
                    throw new \RuntimeException('Enter the delivery confirmation code from the receiver.');
                }
                if (! DeliveryPodOtp::verify((int) $order->id, trim($otp))) {
                    throw new \RuntimeException('Invalid or expired delivery confirmation code.');
                }
            } elseif ($otp !== null && trim($otp) !== '') {
                if (! DeliveryPodOtp::verify((int) $order->id, trim($otp))) {
                    throw new \RuntimeException('Invalid or expired delivery confirmation code.');
                }
            }

            $boxes = $order->middoBoxes()->lockForUpdate()->get();

            foreach ($boxes as $box) {
                if ((int) $box->held_by_user_id !== $riderId) {
                    throw new \RuntimeException("{$box->qr_code_id} is not in your custody.");
                }

                $box->update([
                    'held_by_user_id' => $order->user_id,
                    'kitchen_id' => null,
                    'asset_status' => 'active',
                    'last_scanned_at' => now(),
                ]);

                MiddoBoxLog::create([
                    'order_id' => $order->id,
                    'middo_box_id' => $box->id,
                    'custody_status' => 'with_customer',
                    'log_action' => 'delivered_to_corporate',
                ]);
            }

            $toStatus = $order->amountDue() === 0
                ? OrderTransition::DELIVERED_AND_PAID
                : OrderTransition::DELIVERED;

            OrderTransition::apply($order, $toStatus, [
                'payment_status' => $toStatus === OrderTransition::DELIVERED_AND_PAID ? 'paid' : $order->payment_status,
                'updated_by' => $riderId,
            ]);

            $podAttrs = [];
            if ($podPhotoPath) {
                $podAttrs['pod_photo_path'] = $podPhotoPath;
            }
            if ($otp !== null && trim($otp) !== '') {
                $podAttrs['pod_verified_at'] = now();
            }
            if ($podAttrs !== []) {
                $order->forceFill($podAttrs)->saveQuietly();
            }

            return $order->fresh([
                'menuItem', 'user', 'area', 'deliveryRider', 'orderGroup.kitchen', 'middoBoxes',
            ]);
        });
    }

    /**
     * Cash Due = collection − commission (settled in-kind from float).
     *
     * @return array{order: Order, due_to_middo: int, residual: int, fully_paid: bool, commission: int}
     */
    public static function collectCash(
        int $orderId,
        int $riderId,
        int $cashAmount,
        ?string $shortReason = null,
    ): array {
        return DB::transaction(function () use ($orderId, $riderId, $cashAmount, $shortReason) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if (! $order || (int) $order->delivery_rider_id !== $riderId || ! $order->isDelivered()) {
                throw new \RuntimeException('Order is not available for cash payment.');
            }

            if ($order->isPaid()) {
                throw new \RuntimeException('This order is already paid.');
            }

            $due = $order->amountDue();
            if ($due <= 0) {
                throw new \RuntimeException('Nothing due for this order.');
            }

            if ($cashAmount < 1 || $cashAmount > $due) {
                throw new \RuntimeException('Cash amount must be between ৳1 and ৳'.$due.'.');
            }

            $isShort = $cashAmount < $due;
            if ($isShort && trim((string) $shortReason) === '') {
                throw new \RuntimeException('Add a short reason when collecting less than the full amount due.');
            }

            $priorDue = $order->cash_due_to_middo !== null
                ? max(0, (int) $order->cash_due_to_middo)
                : 0;

            $newPaid = $order->amountPaidValue() + $cashAmount;
            $newCollected = (int) ($order->cash_collected ?? 0) + $cashAmount;
            $fullyPaid = $newPaid >= $order->netTotalAmount();
            $residual = max(0, $order->netTotalAmount() - $newPaid);

            $attrs = [
                'payment_status' => $fullyPaid ? 'paid' : 'pending',
                'amount_paid' => min($newPaid, $order->netTotalAmount()),
                'cash_collected' => $newCollected,
                'payment_method' => OrderPaymentMethod::CASH_ON_DELIVERY,
                'updated_by' => $riderId,
            ];

            if ($fullyPaid) {
                OrderTransition::apply($order, OrderTransition::DELIVERED_AND_PAID, $attrs);
            } else {
                $order->update($attrs);
            }

            $rider = User::query()->whereKey($riderId)->lockForUpdate()->firstOrFail();
            $rider->increment('balance', $cashAmount);

            $commission = OrderMoneyFlow::settleDeliveryCommissionFromCash(
                $order->fresh(),
                $rider->fresh(),
                $cashAmount
            );

            $dueThis = max(0, $cashAmount - $commission);
            $dueToMiddo = $priorDue + $dueThis;
            $order->forceFill(['cash_due_to_middo' => $dueToMiddo])->saveQuietly();

            if ($commission > 0) {
                User::query()->whereKey($riderId)->lockForUpdate()->decrement('balance', $commission);
            }

            if ($isShort && Schema::hasTable('order_logs')) {
                OrderLog::create([
                    'order_id' => $order->id,
                    'event' => 'cash_short_collect',
                    'performed_by' => $riderId,
                    'metadata' => [
                        'cash_collected_this_pass' => $cashAmount,
                        'amount_due_before' => $due,
                        'residual_customer_due' => $residual,
                        'commission_settled' => $commission,
                        'due_to_middo_this_pass' => $dueThis,
                        'reason' => trim((string) $shortReason),
                    ],
                ]);
            }

            return [
                'order' => $order->fresh(['menuItem', 'user', 'area']),
                'due_to_middo' => $dueToMiddo,
                'residual' => $residual,
                'fully_paid' => $fullyPaid,
                'commission' => $commission,
            ];
        });
    }

    /**
     * @param  list<int>  $orderIds
     */
    public static function createCashHandover(
        int $riderId,
        array $orderIds,
        string $target,
        ?string $notes = null,
    ): CashHandover {
        if ($orderIds === []) {
            throw new \RuntimeException('Select at least one paid order to hand over.');
        }

        if (! in_array($target, [CashHandover::TARGET_KITCHEN, CashHandover::TARGET_MIDDO], true)) {
            throw new \RuntimeException('Choose kitchen or Middo as the handover target.');
        }

        return DB::transaction(function () use ($riderId, $orderIds, $target, $notes) {
            $orders = Order::query()
                ->whereIn('id', $orderIds)
                ->where('delivery_rider_id', $riderId)
                ->where('order_status', 'delivered_and_paid')
                ->where('payment_status', 'paid')
                ->whereDoesntHave('cashHandoverOrder')
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== count($orderIds)) {
                throw new \RuntimeException('One or more selected orders are not available for cash handover.');
            }

            $amount = (int) $orders->sum(fn (Order $order) => $order->dueToMiddoAmount());
            if ($amount < 1) {
                throw new \RuntimeException('Selected orders have no Due to Middo left to hand over.');
            }

            $rider = User::query()->whereKey($riderId)->lockForUpdate()->firstOrFail();

            if ((int) $rider->balance < $amount) {
                throw new \RuntimeException('Your Due balance is lower than the selected Due total.');
            }

            $handover = CashHandover::create([
                'rider_id' => $riderId,
                'amount' => $amount,
                'target' => $target,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            foreach ($orders as $order) {
                $due = $order->dueToMiddoAmount();
                if ($due < 1) {
                    continue;
                }
                CashHandoverOrder::create([
                    'cash_handover_id' => $handover->id,
                    'order_id' => $order->id,
                    'amount' => $due,
                ]);
            }

            return $handover->fresh(['items.order.menuItem']);
        });
    }

    public static function acceptWarehouse(int $boxId, int $riderId): MiddoBox
    {
        return KitchenBoxRequestFlow::acceptCustody($boxId, $riderId);
    }

    public static function handToKitchen(int $boxId, int $riderId): MiddoBox
    {
        $warehouseLink = KitchenBoxRequestBox::query()
            ->where('middo_box_id', $boxId)
            ->where('rider_id', $riderId)
            ->whereIn('status', [
                KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP,
                KitchenBoxRequestBox::STATUS_RIDER_ACCEPTED,
            ])
            ->first();

        if ($warehouseLink) {
            if ($warehouseLink->status === KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP) {
                throw new \RuntimeException('Accept custody of this warehouse stock before handing it to the kitchen.');
            }

            return KitchenBoxRequestFlow::handWarehouseStockToKitchen($boxId, $riderId);
        }

        return DB::transaction(function () use ($boxId, $riderId) {
            $box = MiddoBox::query()
                ->with(['orderMiddoBoxes.order.orderGroup.kitchen'])
                ->whereKey($boxId)
                ->lockForUpdate()
                ->first();

            if (! $box || (int) $box->held_by_user_id !== $riderId) {
                throw new \RuntimeException('This box is not in your custody.');
            }

            $order = $box->orderMiddoBoxes->first()?->order;
            $kitchenId = $order?->orderGroup?->kitchen_id ?: $box->return_kitchen_id;

            if (! $kitchenId) {
                throw new \RuntimeException('Destination kitchen is unknown for this box.');
            }

            if ($box->kitchen_id !== null && (int) $box->held_by_user_id !== (int) $box->kitchen_id) {
                throw new \RuntimeException('This box is already marked as handed to a kitchen.');
            }

            $box->update([
                'kitchen_id' => (int) $kitchenId,
                'held_by_user_id' => $riderId,
                'asset_status' => 'active',
                'last_scanned_at' => now(),
            ]);

            MiddoBoxLog::create([
                'order_id' => $order?->id,
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'returned_to_kitchen',
            ]);

            return $box->fresh();
        });
    }

    public static function acceptKitchenReturn(int $boxId, int $riderId): MiddoBox
    {
        return MiddoBoxKitchenActions::acceptWarehouseReturnCustody($boxId, $riderId);
    }

    public static function handToOps(int $boxId, int $riderId): MiddoBox
    {
        $box = MiddoBox::query()->findOrFail($boxId);

        return MiddoBoxKitchenActions::handToOpsByRider($box, $riderId);
    }

    public static function collectEmpty(int $boxId, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($boxId, $riderId) {
            $box = MiddoBox::query()->with('heldByUser.role')->whereKey($boxId)->lockForUpdate()->first();
            if (! $box || (int) $box->pickup_rider_id !== $riderId) {
                throw new \RuntimeException('This empty-box collect is not assigned to you.');
            }
            if ($box->heldByUser?->role?->name !== 'corporate') {
                throw new \RuntimeException('This box is not with the corporate customer.');
            }

            $holderName = $box->heldByUser?->name ?? 'corporate';
            $box->update([
                'held_by_user_id' => $riderId,
                'pickup_rider_id' => $riderId,
                'ready_for_pickup' => false,
                'kitchen_id' => null,
                'asset_status' => 'active',
                'last_scanned_at' => now(),
            ]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'collected_by_rider',
                'log_action' => 'picked_from_corporate_by_delivery',
                'notes' => 'Collected empty box from '.$holderName,
                'performed_by' => $riderId,
            ]);

            $rider = User::query()->find($riderId);
            if ($rider) {
                $perBox = RiderCommission::forSettingsRun($rider, DeliveryRunType::CORPORATE_TO_KITCHEN);
                MiddoOperatingCosts::bookRiderCommission(
                    $rider,
                    DeliveryRunType::CORPORATE_TO_KITCHEN,
                    $perBox,
                    MiddoBox::class,
                    (int) $box->id,
                    'Corporate→kitchen box #'.($box->qr_code_id ?? $box->id),
                    $riderId
                );
            }

            return $box->fresh();
        });
    }

    /**
     * @return array{accepted: int, errors: list<string>}
     */
    public static function acceptAllForRequest(int $requestId, int $riderId): array
    {
        $ids = KitchenBoxRequestBox::query()
            ->where('kitchen_box_request_id', $requestId)
            ->where('rider_id', $riderId)
            ->where('status', KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP)
            ->pluck('middo_box_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $accepted = 0;
        $errors = [];
        foreach ($ids as $boxId) {
            try {
                KitchenBoxRequestFlow::acceptCustody($boxId, $riderId);
                $accepted++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage() ?: 'Could not accept a box.';
            }
        }

        return ['accepted' => $accepted, 'errors' => $errors];
    }

    /**
     * @return array{handed: int, errors: list<string>}
     */
    public static function handAllForRequest(int $requestId, int $riderId): array
    {
        $ids = KitchenBoxRequestBox::query()
            ->where('kitchen_box_request_id', $requestId)
            ->where('rider_id', $riderId)
            ->where('status', KitchenBoxRequestBox::STATUS_RIDER_ACCEPTED)
            ->pluck('middo_box_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $handed = 0;
        $errors = [];
        foreach ($ids as $boxId) {
            try {
                KitchenBoxRequestFlow::handWarehouseStockToKitchen($boxId, $riderId);
                $handed++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage() ?: 'Could not hand a box to kitchen.';
            }
        }

        return ['handed' => $handed, 'errors' => $errors];
    }

    public static function startCustomRun(int $runId, int $riderId): CustomRun
    {
        return DB::transaction(function () use ($runId, $riderId) {
            $run = CustomRun::query()->whereKey($runId)->lockForUpdate()->first();
            if (! $run || ! $run->isPending()) {
                throw new \RuntimeException('This custom run is no longer available to start.');
            }

            $rider = User::query()->findOrFail($riderId);

            if (! $rider->canAcceptNewRuns()) {
                throw new \RuntimeException('You are not on shift. Set On shift on the dashboard before starting runs.');
            }

            if ($run->rider_user_id === null || (int) $run->rider_user_id !== $riderId) {
                throw new \RuntimeException('This custom run is not assigned to you.');
            }

            $run->update([
                'rider_user_id' => $riderId,
                'status' => CustomRun::STATUS_STARTED,
                'started_at' => now(),
            ]);

            $amount = (int) $run->commission_amount;
            if ($amount > 0) {
                MiddoOperatingCosts::bookRiderCommission(
                    $rider,
                    DeliveryRunType::CUSTOM,
                    $amount,
                    CustomRun::class,
                    (int) $run->id,
                    'Custom run #'.$run->id.': '.$run->label(),
                    $riderId
                );
            }

            return $run->fresh(['area', 'rider']);
        });
    }

    public static function completeCustomRun(int $runId, int $riderId): CustomRun
    {
        return DB::transaction(function () use ($runId, $riderId) {
            $run = CustomRun::query()->whereKey($runId)->lockForUpdate()->first();
            if (! $run || ! $run->isStarted()) {
                throw new \RuntimeException('This custom run is not in progress.');
            }
            if ((int) $run->rider_user_id !== $riderId) {
                throw new \RuntimeException('Only the assigned rider can complete this run.');
            }

            $run->update([
                'status' => CustomRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return $run->fresh(['area', 'rider']);
        });
    }

    public static function requestWithdrawal(
        User $rider,
        ?string $notes = null,
        ?string $payoutChannel = null,
    ): RiderWithdrawalRequest {
        $riderId = (int) $rider->id;
        $channel = $payoutChannel
            ?? $rider->preferredPayoutChannel()
            ?? PayoutChannel::defaultPartnerChannel();

        if (! $rider->hasCompletePayoutMethod($channel)) {
            throw new \RuntimeException(
                'Add your '.PayoutChannel::label($channel).' details in profile before requesting this payout.'
            );
        }

        $details = $rider->payoutDetailsFor($channel);
        PayoutChannel::assertValid($channel, $details);

        $due = (int) $rider->balance;
        if ($due > 0) {
            throw new \RuntimeException('Hand over Due to Middo cash first, then request payment.');
        }

        $amount = RiderAccountLedger::balance($riderId);
        if ($amount < 1) {
            throw new \RuntimeException('Nothing to withdraw — Middo does not currently owe you.');
        }

        return RiderMoneyService::requestWithdrawal(
            $riderId,
            $amount,
            $channel,
            $details,
            $notes,
            $riderId,
        );
    }

    public static function setShift(User $rider, string $status): User
    {
        if (! RiderShift::isValid($status)) {
            throw new \RuntimeException('Invalid shift status.');
        }

        if (! $rider->isDelivery()) {
            throw new \RuntimeException('Only delivery riders can set shift status.');
        }

        $rider->update(['rider_shift_status' => $status]);

        return $rider->fresh(['role', 'area', 'city']);
    }

    /**
     * @return array{boxes: list<array<string, mixed>>, run_groups: list<array<string, mixed>>}
     */
    public static function pendingBoxesPayload(User $rider): array
    {
        $riderId = (int) $rider->id;
        $allBoxes = RiderPendingBoxes::boxesForRider($riderId);
        $stagedByBoxId = RiderPendingBoxes::stagedLinksForRider($riderId);
        $kitchenToOpsByBoxId = RiderPendingBoxes::kitchenToOpsLinksForRider($riderId);

        $latestActions = MiddoBoxLog::query()
            ->whereIn('middo_box_id', $allBoxes->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('middo_box_id')
            ->keyBy('middo_box_id');

        $requestLinksByBoxId = KitchenBoxRequestBox::query()
            ->whereIn('middo_box_id', $allBoxes->pluck('id'))
            ->where('rider_id', $riderId)
            ->orderByDesc('id')
            ->get()
            ->unique('middo_box_id')
            ->keyBy('middo_box_id');

        $nodes = $allBoxes
            ->map(function (MiddoBox $box) use (
                $latestActions,
                $stagedByBoxId,
                $kitchenToOpsByBoxId,
                $requestLinksByBoxId,
                $riderId
            ) {
                return self::mapPendingBoxNode(
                    $box,
                    $latestActions,
                    $stagedByBoxId,
                    $kitchenToOpsByBoxId,
                    $requestLinksByBoxId,
                    $riderId
                );
            })
            ->values();

        $runGroups = $nodes
            ->groupBy('run_group_key')
            ->map(function (Collection $groupNodes, $key) {
                $first = $groupNodes->first();

                return [
                    'key' => $key,
                    'title' => $first['run_group_title'],
                    'request_id' => $first['request_id'],
                    'label' => $first['run_group_title'],
                    'kitchen_name' => $first['kitchen_name'],
                    'kitchen_mobile' => $first['kitchen_mobile'],
                    'kitchen_address' => $first['kitchen_address'],
                    'box_count' => $groupNodes->count(),
                    'can_accept_all' => $groupNodes->contains(fn (array $n) => $n['can_accept_warehouse']),
                    'can_hand_all' => $groupNodes->contains(fn (array $n) => $n['can_hand_to_kitchen'] && ($n['can_hand_warehouse_stock'] ?? false)),
                    'accept_all_ids' => $groupNodes
                        ->filter(fn (array $n) => $n['can_accept_warehouse'])
                        ->pluck('id')
                        ->values()
                        ->all(),
                    'hand_all_ids' => $groupNodes
                        ->filter(fn (array $n) => $n['can_hand_warehouse_stock'] ?? false)
                        ->pluck('id')
                        ->values()
                        ->all(),
                    'boxes' => $groupNodes->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'boxes' => $nodes->all(),
            'run_groups' => $runGroups,
            // Flutter 0.1 read `requests`; keep alias for bulk groups.
            'requests' => $runGroups,
        ];
    }

    /**
     * @param  Collection<int, MiddoBoxLog>  $latestActions
     * @param  Collection<int, mixed>  $stagedByBoxId
     * @param  Collection<int, mixed>  $kitchenToOpsByBoxId
     * @param  Collection<int, KitchenBoxRequestBox>  $requestLinksByBoxId
     * @return array<string, mixed>
     */
    protected static function mapPendingBoxNode(
        MiddoBox $box,
        Collection $latestActions,
        Collection $stagedByBoxId,
        Collection $kitchenToOpsByBoxId,
        Collection $requestLinksByBoxId,
        int $riderId,
    ): array {
        $linkedOrder = $box->orderMiddoBoxes->first()?->order;
        $kitchenReturn = $kitchenToOpsByBoxId->get($box->id);
        $stagedLink = $stagedByBoxId->get($box->id);
        $destinationKitchen = $box->kitchen
            ?? $kitchenReturn?->kitchen
            ?? $stagedLink?->request?->kitchen
            ?? $linkedOrder?->orderGroup?->kitchen;
        $latestAction = $latestActions->get($box->id)?->log_action;

        $isEmptyBoxCollect = (int) ($box->pickup_rider_id ?? 0) === $riderId
            && $box->heldByUser?->role?->name === 'corporate'
            && (int) $box->held_by_user_id !== $riderId;

        $isStagedPickup = $stagedByBoxId->has($box->id);
        $isDispatchedKitchenReturn = $kitchenReturn?->status === KitchenWarehouseHandoff::STATUS_DISPATCHED
            && (int) $kitchenReturn->rider_id === $riderId;
        $isInTransitKitchenReturn = $kitchenReturn?->status === KitchenWarehouseHandoff::STATUS_IN_TRANSIT
            && (int) $box->held_by_user_id === $riderId;
        $isHandedAwaitingOpsReceive = $kitchenReturn?->status === KitchenWarehouseHandoff::STATUS_HANDED_TO_OPS
            && (int) $box->held_by_user_id === $riderId;
        $enRouteToWarehouse = ($isInTransitKitchenReturn || $isHandedAwaitingOpsReceive)
            || in_array($latestAction, ['dispatched_to_warehouse', 'rider_accepted_warehouse_return', 'handed_to_ops_warehouse'], true)
                && (int) $box->held_by_user_id === $riderId
                && ! $linkedOrder;

        $isAcceptedWarehouseStock = $latestAction === 'rider_accepted_kitchen_stock'
            && (int) $box->held_by_user_id === $riderId
            && $box->kitchen_id !== null
            && ! $linkedOrder;

        $canAcceptPickup = $isStagedPickup;
        $canCollectEmptyBox = $isEmptyBoxCollect;
        $canAcceptKitchenReturn = (bool) $isDispatchedKitchenReturn;
        $canHandWarehouseStock = $isAcceptedWarehouseStock;
        $canHandToKitchen = ! $enRouteToWarehouse
            && ! $isStagedPickup
            && ! $kitchenReturn
            && ! $isAcceptedWarehouseStock
            && ! $isEmptyBoxCollect
            && $box->kitchen_id === null
            && (int) $box->held_by_user_id === $riderId
            && ($linkedOrder !== null || (int) ($box->return_kitchen_id ?? 0) > 0);

        $canDeliverToWarehouse = ($enRouteToWarehouse || $isInTransitKitchenReturn)
            && ! $isHandedAwaitingOpsReceive
            && $latestAction !== 'handed_to_ops_warehouse';

        $opsKitchenRequestId = $stagedLink?->kitchen_box_request_id
            ?? $requestLinksByBoxId->get($box->id)?->kitchen_box_request_id;

        $runGroupKey = $opsKitchenRequestId
            ? 'ops-kitchen-'.$opsKitchenRequestId
            : ($kitchenReturn
                ? 'kitchen-ops-'.($kitchenReturn->kitchen_id ?? 'x').'-'.$kitchenReturn->status
                : ($isEmptyBoxCollect
                    ? 'empty-box-'.($box->held_by_user_id ?? 'x')
                    : 'solo-'.$box->id));

        $runGroupTitle = $opsKitchenRequestId
            ? 'Ops→kitchen run #'.$opsKitchenRequestId
            : ($kitchenReturn
                ? 'Kitchen→ops return'
                : ($isEmptyBoxCollect ? 'Corporate→kitchen empty box' : 'Single box'));

        $action = match (true) {
            $canAcceptPickup => 'accept_warehouse',
            $canHandWarehouseStock || $canHandToKitchen => 'hand_to_kitchen',
            $canAcceptKitchenReturn => 'accept_kitchen_return',
            $canDeliverToWarehouse => 'hand_to_ops',
            $canCollectEmptyBox => 'collect_empty',
            default => 'waiting',
        };

        $actionLabel = match ($action) {
            'accept_warehouse' => 'Accept from warehouse',
            'hand_to_kitchen' => 'Hand to kitchen',
            'accept_kitchen_return' => 'Accept kitchen return',
            'hand_to_ops' => 'Hand to ops',
            'collect_empty' => 'Collect empty',
            default => 'Waiting',
        };

        $showWarehouseDestination = $enRouteToWarehouse
            || $isDispatchedKitchenReturn
            || $isInTransitKitchenReturn
            || $isHandedAwaitingOpsReceive;

        $location = $isEmptyBoxCollect
            ? ($box->heldByUser?->name ?? 'Corporate')
            : ($showWarehouseDestination
                ? (($destinationKitchen?->name ? $destinationKitchen->name.' → ' : '').'Middo warehouse')
                : ($destinationKitchen?->name ?? 'In custody'));

        return DeliveryApiPresenter::box($box, [
            'action' => $action,
            'action_label' => $actionLabel,
            'location' => $location,
            'run_label' => $actionLabel,
            'run_group_key' => $runGroupKey,
            'run_group_title' => $runGroupTitle,
            'request_id' => $opsKitchenRequestId ? (int) $opsKitchenRequestId : null,
            'kitchen_name' => $isEmptyBoxCollect
                ? ($box->heldByUser?->name ?? 'Corporate')
                : ($showWarehouseDestination
                    ? (($destinationKitchen?->name ? $destinationKitchen->name.' → ' : '').'Middo warehouse')
                    : $destinationKitchen?->name),
            'kitchen_mobile' => $isEmptyBoxCollect
                ? $box->heldByUser?->mobile
                : ($showWarehouseDestination && ! $destinationKitchen
                    ? null
                    : $destinationKitchen?->mobile),
            'kitchen_address' => $isEmptyBoxCollect
                ? $box->heldByUser?->address
                : ($showWarehouseDestination && ! $destinationKitchen
                    ? null
                    : $destinationKitchen?->address),
            'order_id' => $linkedOrder?->id,
            'menu_name' => $linkedOrder?->menuItem?->name,
            'customer_name' => $linkedOrder
                ? $linkedOrder->partyPayload()['customer_name']
                : null,
            'can_accept_warehouse' => (bool) $canAcceptPickup,
            'can_hand_to_kitchen' => (bool) ($canHandWarehouseStock || $canHandToKitchen),
            'can_hand_warehouse_stock' => (bool) $canHandWarehouseStock,
            'can_accept_kitchen_return' => (bool) $canAcceptKitchenReturn,
            'can_hand_to_ops' => (bool) $canDeliverToWarehouse,
            'can_collect_empty' => (bool) $canCollectEmptyBox,
            'can_claim_kitchen_return' => false,
            'awaiting_ops_receive' => (bool) (
                $isHandedAwaitingOpsReceive
                || $latestAction === 'handed_to_ops_warehouse'
            ),
        ]);
    }

    public static function updateRunEta(int $orderId, int $riderId, int $etaMinutes): Order
    {
        $order = Order::query()->find($orderId);
        if (! $order || ! $order->isAssignedToRider($riderId)) {
            throw new \RuntimeException('Order is not assigned to you.');
        }
        if ($order->isDelivered() || $order->order_status === 'cancelled') {
            throw new \RuntimeException('ETA can only be set while the run is active.');
        }

        Cache::put(self::etaCacheKey($orderId), [
            'minutes' => $etaMinutes,
            'updated_at' => now()->toIso8601String(),
            'rider_id' => $riderId,
        ], now()->addHours(8));

        return $order->fresh(['menuItem', 'user', 'area', 'deliveryRider', 'orderGroup.kitchen', 'middoBoxes']);
    }

    /**
     * @return array{order: Order, payment_url: string, phone: string, sms_sent: bool, message: string}
     */
    public static function sendPaymentLink(int $orderId, int $riderId, ?string $phone = null): array
    {
        $order = Order::query()->with('menuItem')->find($orderId);
        if (! $order || (int) $order->delivery_rider_id !== $riderId || ! $order->isDelivered() || $order->isPaid()) {
            throw new \RuntimeException('Order is not available for online payment.');
        }

        $phone = $phone ?: (string) ($order->receiver_mobile ?: '');
        if (! preg_match('/^01[3-9]\d{8}$/', $phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Enter a valid 11-digit BD mobile number (e.g. 01710123456).'],
            ]);
        }

        $due = $order->amountDue();
        if ($due < 1) {
            throw new \RuntimeException('Nothing due for this order.');
        }

        $paymentUrl = URL::temporarySignedRoute(
            'public.order-payment',
            now()->addDays(3),
            ['order' => $order->id]
        );

        $menu = $order->menuItem?->name ?? 'order';
        $message = "Middo payment for order #{$order->id} ({$menu}): ৳{$due} due. Pay here: {$paymentUrl}";
        $sent = MimSms::send($phone, $message);

        if (! $sent && ! config('app.debug')) {
            throw new \RuntimeException('Could not send payment SMS. Please try again.');
        }

        $order->update(['updated_by' => $riderId]);

        return [
            'order' => $order->fresh(['menuItem', 'user', 'area', 'deliveryRider']),
            'payment_url' => $paymentUrl,
            'phone' => $phone,
            'sms_sent' => (bool) $sent,
            'message' => config('app.debug') && ! $sent
                ? 'Payment link prepared (SMS skipped in debug/unavailable).'
                : 'Payment link sent to '.$phone.'.',
        ];
    }

    public static function etaCacheKey(int $orderId): string
    {
        return 'delivery_run_eta:'.$orderId;
    }

    /**
     * @return array{minutes: int|null, updated_at: string|null}
     */
    public static function etaForOrder(int $orderId): array
    {
        $cached = Cache::get(self::etaCacheKey($orderId));
        if (! is_array($cached)) {
            return ['minutes' => null, 'updated_at' => null];
        }

        return [
            'minutes' => isset($cached['minutes']) ? (int) $cached['minutes'] : null,
            'updated_at' => isset($cached['updated_at']) ? (string) $cached['updated_at'] : null,
        ];
    }
}
