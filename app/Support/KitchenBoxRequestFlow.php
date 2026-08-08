<?php

namespace App\Support;

use App\Models\KitchenBoxRequest;
use App\Models\KitchenBoxRequestBox;
use App\Models\KitchenBoxRequestLog;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KitchenBoxRequestFlow
{
    public static function logRequestEvent(
        KitchenBoxRequest $request,
        string $event,
        ?int $performedBy = null,
        ?string $note = null,
        ?array $meta = null
    ): KitchenBoxRequestLog {
        return KitchenBoxRequestLog::create([
            'kitchen_box_request_id' => $request->id,
            'event' => $event,
            'note' => $note,
            'meta' => $meta,
            'performed_by' => $performedBy,
        ]);
    }

    /**
     * Stage warehouse boxes as ready for a rider to pick up against open kitchen requests (FIFO).
     *
     * @param  list<int>|\Illuminate\Support\Collection<int, int>  $boxIds
     * @return array{boxes: Collection<int, MiddoBox>, rider: User, kitchen: User, count: int}
     */
    public static function stageForPickup(
        array|Collection $boxIds,
        int $kitchenId,
        int $riderId,
        ?int $opsUserId = null
    ): array {
        return DB::transaction(function () use ($boxIds, $kitchenId, $riderId, $opsUserId) {
            $ids = collect($boxIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
            if ($ids->isEmpty()) {
                throw new \InvalidArgumentException('No boxes selected.');
            }

            $boxes = MiddoBox::query()
                ->availableForKitchenStaging()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($boxes->isEmpty()) {
                throw new \RuntimeException('No warehouse boxes were available to stage. Staged or in-transit boxes cannot be selected again.');
            }

            if ($boxes->count() !== $ids->count()) {
                throw new \RuntimeException('One or more selected boxes are already staged or not free warehouse stock.');
            }

            // Unique middo_box_id: clear completed links so returned stock can be staged again.
            KitchenBoxRequestBox::query()
                ->whereIn('middo_box_id', $boxes->pluck('id'))
                ->where('status', KitchenBoxRequestBox::STATUS_RECEIVED)
                ->delete();

            $shipQty = $boxes->count();
            $allocations = self::allocateAgainstOpenRequests($kitchenId, $shipQty, $opsUserId);

            $rider = User::query()->findOrFail($riderId);
            $kitchen = User::query()->findOrFail($kitchenId);

            $boxQueue = $boxes->values();
            $cursor = 0;
            foreach ($allocations as $allocation) {
                /** @var KitchenBoxRequest $request */
                $request = $allocation['request'];
                $take = (int) $allocation['qty'];
                $chunk = $boxQueue->slice($cursor, $take);
                $cursor += $take;

                foreach ($chunk as $box) {
                    KitchenBoxRequestBox::create([
                        'kitchen_box_request_id' => $request->id,
                        'middo_box_id' => $box->id,
                        'rider_id' => $riderId,
                        'status' => KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP,
                    ]);

                    MiddoBoxLog::create([
                        'middo_box_id' => $box->id,
                        'custody_status' => 'warehouse',
                        'log_action' => 'staged_for_kitchen_pickup',
                        'notes' => 'Ready for rider pickup → '.$kitchen->name,
                        'performed_by' => $opsUserId,
                    ]);
                }

                self::logRequestEvent(
                    $request,
                    KitchenBoxRequestLog::EVENT_STAGED_FOR_PICKUP,
                    $opsUserId,
                    null,
                    [
                        'qty' => $take,
                        'box_ids' => $chunk->pluck('id')->values()->all(),
                        'rider_id' => $riderId,
                    ]
                );
            }

            return [
                'boxes' => $boxes,
                'rider' => $rider,
                'kitchen' => $kitchen,
                'count' => $shipQty,
            ];
        });
    }

    /**
     * @return list<array{request: KitchenBoxRequest, qty: int}>
     */
    protected static function allocateAgainstOpenRequests(int $kitchenId, int $shipQty, ?int $opsUserId): array
    {
        if ($shipQty < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        $requests = KitchenBoxRequest::query()
            ->open()
            ->where('kitchen_id', $kitchenId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (int) $requests->sum(fn (KitchenBoxRequest $r) => $r->remainingQuantity());
        if ($available < 1) {
            throw new \RuntimeException('This kitchen has no open box request. Ask them to Request box first.');
        }

        if ($shipQty > $available) {
            throw new \RuntimeException(sprintf(
                'Cannot stage %d boxes — kitchen only requested %d more.',
                $shipQty,
                $available
            ));
        }

        $remaining = $shipQty;
        $allocations = [];

        foreach ($requests as $request) {
            if ($remaining < 1) {
                break;
            }

            $slot = $request->remainingQuantity();
            if ($slot < 1) {
                continue;
            }

            $take = min($remaining, $slot);
            $request->update([
                'allocated_qty' => (int) $request->allocated_qty + $take,
                'reviewed_by' => $opsUserId ?? $request->reviewed_by,
                'reviewed_at' => now(),
            ]);

            $allocations[] = ['request' => $request->fresh(), 'qty' => $take];
            $remaining -= $take;
        }

        return $allocations;
    }

    public static function acceptCustody(int $boxId, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($boxId, $riderId) {
            $link = KitchenBoxRequestBox::query()
                ->with(['request.kitchen', 'box'])
                ->where('middo_box_id', $boxId)
                ->where('rider_id', $riderId)
                ->where('status', KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP)
                ->lockForUpdate()
                ->first();

            if (! $link) {
                throw new \RuntimeException('This box is not staged for your pickup.');
            }

            $box = MiddoBox::query()->whereKey($boxId)->lockForUpdate()->first();
            if (! $box || $box->asset_status !== 'at_middo_warehouse' || $box->held_by_user_id !== null) {
                throw new \RuntimeException('This box is not available at the warehouse.');
            }

            $request = $link->request;
            $kitchenId = (int) $request->kitchen_id;

            $box->update([
                'held_by_user_id' => $riderId,
                'kitchen_id' => $kitchenId,
                'asset_status' => 'active',
                'last_scanned_at' => now(),
            ]);

            $link->update(['status' => KitchenBoxRequestBox::STATUS_RIDER_ACCEPTED]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'rider_accepted_kitchen_stock',
                'notes' => 'Rider accepted warehouse stock for kitchen',
                'performed_by' => $riderId,
            ]);

            self::logRequestEvent(
                $request,
                KitchenBoxRequestLog::EVENT_RIDER_ACCEPTED,
                $riderId,
                null,
                ['box_id' => $box->id]
            );

            $rider = User::query()->findOrFail($riderId);
            $perBox = RiderCommission::forSettingsRun($rider, DeliveryRunType::OPS_TO_KITCHEN);
            MiddoOperatingCosts::bookRiderCommission(
                $rider,
                DeliveryRunType::OPS_TO_KITCHEN,
                $perBox,
                MiddoBox::class,
                (int) $box->id,
                'Ops→kitchen box #'.($box->qr_code_id ?? $box->id),
                $rider->id
            );

            return $box->fresh();
        });
    }

    public static function handWarehouseStockToKitchen(int $boxId, int $riderId): MiddoBox
    {
        return DB::transaction(function () use ($boxId, $riderId) {
            $link = KitchenBoxRequestBox::query()
                ->with('request')
                ->where('middo_box_id', $boxId)
                ->where('rider_id', $riderId)
                ->where('status', KitchenBoxRequestBox::STATUS_RIDER_ACCEPTED)
                ->lockForUpdate()
                ->first();

            if (! $link) {
                throw new \RuntimeException('Accept custody of this warehouse stock before handing it to the kitchen.');
            }

            $box = MiddoBox::query()->whereKey($boxId)->lockForUpdate()->first();
            if (! $box || (int) $box->held_by_user_id !== $riderId) {
                throw new \RuntimeException('This box is not in your custody.');
            }

            $kitchenId = (int) $link->request->kitchen_id;
            if ((int) $box->kitchen_id !== $kitchenId) {
                $box->update(['kitchen_id' => $kitchenId]);
            }

            $link->update(['status' => KitchenBoxRequestBox::STATUS_HANDED_TO_KITCHEN]);

            MiddoBoxLog::create([
                'middo_box_id' => $box->id,
                'custody_status' => 'in_transit',
                'log_action' => 'handed_to_kitchen_stock',
                'notes' => 'Rider handed warehouse stock at kitchen',
                'performed_by' => $riderId,
            ]);

            self::logRequestEvent(
                $link->request,
                KitchenBoxRequestLog::EVENT_HANDED_TO_KITCHEN,
                $riderId,
                null,
                ['box_id' => $box->id]
            );

            return $box->fresh();
        });
    }

    public static function markReceivedAtKitchen(MiddoBox $box, int $kitchenId): void
    {
        $link = KitchenBoxRequestBox::query()
            ->with('request')
            ->where('middo_box_id', $box->id)
            ->whereIn('status', [
                KitchenBoxRequestBox::STATUS_HANDED_TO_KITCHEN,
                KitchenBoxRequestBox::STATUS_RIDER_ACCEPTED,
            ])
            ->lockForUpdate()
            ->first();

        if (! $link) {
            return;
        }

        if ((int) $link->request->kitchen_id !== $kitchenId) {
            return;
        }

        // Warehouse stock must be handed before kitchen can confirm receive.
        if ($link->status !== KitchenBoxRequestBox::STATUS_HANDED_TO_KITCHEN) {
            throw new \RuntimeException('Wait for the rider to hand this box before confirming receive.');
        }

        $link->update(['status' => KitchenBoxRequestBox::STATUS_RECEIVED]);

        self::logRequestEvent(
            $link->request,
            KitchenBoxRequestLog::EVENT_RECEIVED_AT_KITCHEN,
            $kitchenId,
            null,
            ['box_id' => $box->id]
        );
    }

    public static function closeRequest(KitchenBoxRequest $request, int $opsUserId, string $note): KitchenBoxRequest
    {
        return DB::transaction(function () use ($request, $opsUserId, $note) {
            $request = KitchenBoxRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isOpen()) {
                throw new \RuntimeException('That box request is no longer open.');
            }

            $openLinks = KitchenBoxRequestBox::query()
                ->where('kitchen_box_request_id', $request->id)
                ->where('status', '!=', KitchenBoxRequestBox::STATUS_RECEIVED)
                ->count();

            if ($openLinks > 0) {
                throw new \RuntimeException('Cannot close yet — some staged boxes are still in transit or awaiting kitchen receive.');
            }

            if ((int) $request->allocated_qty < 1) {
                throw new \RuntimeException('Stage and deliver at least one box before closing, or cancel the request.');
            }

            $note = trim($note);
            if ($note === '') {
                throw new \RuntimeException('A close note is required.');
            }

            $request->update([
                'status' => KitchenBoxRequest::STATUS_CLOSED,
                'closed_note' => $note,
                'closed_by' => $opsUserId,
                'closed_at' => now(),
                'reviewed_by' => $opsUserId,
                'reviewed_at' => now(),
            ]);

            self::logRequestEvent(
                $request,
                KitchenBoxRequestLog::EVENT_CLOSED,
                $opsUserId,
                $note,
                ['allocated_qty' => (int) $request->allocated_qty, 'requested_qty' => (int) $request->quantity]
            );

            return $request->fresh();
        });
    }

    public static function cancelRequest(KitchenBoxRequest $request, int $userId, ?string $note = null): KitchenBoxRequest
    {
        return DB::transaction(function () use ($request, $userId, $note) {
            $request = KitchenBoxRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isOpen()) {
                throw new \RuntimeException('That box request is no longer open.');
            }

            if ((int) $request->allocated_qty > 0) {
                throw new \RuntimeException('Cannot cancel — boxes are already staged against this request. Finish the handoff and close with a note instead.');
            }

            $request->update([
                'status' => KitchenBoxRequest::STATUS_CANCELLED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            self::logRequestEvent(
                $request,
                KitchenBoxRequestLog::EVENT_CANCELLED,
                $userId,
                $note,
                null
            );

            return $request->fresh();
        });
    }

    public static function latestBoxAction(int $boxId): ?string
    {
        return MiddoBoxLog::query()
            ->where('middo_box_id', $boxId)
            ->orderByDesc('id')
            ->value('log_action');
    }

    public static function isHandedWarehouseStock(?string $logAction): bool
    {
        return in_array($logAction, ['handed_to_kitchen_stock', 'dispatched_to_kitchen'], true);
    }
}
