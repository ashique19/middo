<?php

namespace App\Livewire\Delivery;

use App\Models\KitchenBoxRequestBox;
use App\Models\KitchenWarehouseHandoff;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Support\KitchenBoxRequestFlow;
use App\Support\MiddoBoxKitchenActions;
use App\Support\RiderPendingBoxes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PendingBoxRuns extends Component
{
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function acceptWarehouseStock(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $box = KitchenBoxRequestFlow::acceptCustody($boxId, (int) Auth::id());
            $this->statusMessage = "{$box->qr_code_id} accepted — deliver to kitchen, then mark handed.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept this box.';
        }
    }

    public function claimKitchenReturn(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $box = MiddoBoxKitchenActions::claimWarehouseRun($boxId, (int) Auth::id());
            $this->statusMessage = "{$box->qr_code_id} run claimed — wait for kitchen to dispatch.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not claim this run.';
        }
    }

    public function acceptKitchenReturn(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $box = MiddoBoxKitchenActions::acceptWarehouseReturnCustody($boxId, (int) Auth::id());
            $this->statusMessage = "{$box->qr_code_id} accepted — run started. Hand to Middo ops when you arrive.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept this box.';
        }
    }

    public function handWarehouseStock(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $box = KitchenBoxRequestFlow::handWarehouseStockToKitchen($boxId, (int) Auth::id());
            $this->statusMessage = "{$box->qr_code_id} handed to kitchen. Waiting for kitchen confirmation.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not hand box to kitchen.';
        }
    }

    public function handToKitchen(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $riderId = (int) Auth::id();

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
                $this->errorMessage = 'Accept custody of this warehouse stock before handing it to the kitchen.';

                return;
            }
            $this->handWarehouseStock($boxId);

            return;
        }

        try {
            $qr = DB::transaction(function () use ($boxId, $riderId) {
                $box = MiddoBox::query()
                    ->with(['orderMiddoBoxes.order.orderGroup.kitchen'])
                    ->whereKey($boxId)
                    ->lockForUpdate()
                    ->first();

                if (! $box || (int) $box->held_by_user_id !== $riderId) {
                    throw new \RuntimeException('This box is not in your custody.');
                }

                if ($box->kitchen_id !== null && (int) $box->held_by_user_id !== (int) $box->kitchen_id) {
                    throw new \RuntimeException('This box is already marked as handed to a kitchen.');
                }

                $order = $box->orderMiddoBoxes->first()?->order;
                $kitchenId = $order?->orderGroup?->kitchen_id;

                if (! $kitchenId) {
                    throw new \RuntimeException('Destination kitchen is unknown for this box. Ensure the order group has a kitchen.');
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

                return $box->qr_code_id;
            });

            $this->statusMessage = "{$qr} handed to kitchen. Waiting for kitchen confirmation.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not hand box to kitchen.';
        }
    }

    public function deliverToWarehouse(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $box = MiddoBox::query()->findOrFail($boxId);
            $delivered = MiddoBoxKitchenActions::handToOpsByRider($box, (int) Auth::id());
            $this->statusMessage = "{$delivered->qr_code_id} handed to Middo ops — waiting for ops to mark received.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not hand box to ops.';
        }
    }

    public function render()
    {
        $riderId = (int) Auth::id();

        $allBoxes = RiderPendingBoxes::boxesForRider($riderId);
        $stagedByBoxId = RiderPendingBoxes::stagedLinksForRider($riderId);
        $kitchenToOpsByBoxId = RiderPendingBoxes::kitchenToOpsLinksForRider($riderId);

        $page = max(1, (int) $this->getPage());
        $perPage = 20;
        $total = $allBoxes->count();
        $items = $allBoxes->slice(($page - 1) * $perPage, $perPage)->values();
        $boxes = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $latestActions = MiddoBoxLog::query()
            ->whereIn('middo_box_id', $items->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('middo_box_id')
            ->keyBy('middo_box_id');

        $requestLinksByBoxId = KitchenBoxRequestBox::query()
            ->whereIn('middo_box_id', $items->pluck('id'))
            ->where('rider_id', $riderId)
            ->orderByDesc('id')
            ->get()
            ->unique('middo_box_id')
            ->keyBy('middo_box_id');

        $nodes = $items
            ->map(function (MiddoBox $box) use ($latestActions, $stagedByBoxId, $kitchenToOpsByBoxId, $requestLinksByBoxId, $riderId) {
                $linkedOrder = $box->orderMiddoBoxes->first()?->order;
                $kitchenReturn = $kitchenToOpsByBoxId->get($box->id);
                $stagedLink = $stagedByBoxId->get($box->id);
                $destinationKitchen = $box->kitchen
                    ?? $kitchenReturn?->kitchen
                    ?? $stagedLink?->request?->kitchen
                    ?? $linkedOrder?->orderGroup?->kitchen;
                $latestAction = $latestActions->get($box->id)?->log_action;

                $isStagedPickup = $stagedByBoxId->has($box->id);
                $isClaimableKitchenReturn = $kitchenReturn?->status === KitchenWarehouseHandoff::STATUS_RUN_REQUESTED;
                $isClaimedWaitingDispatch = $kitchenReturn?->status === KitchenWarehouseHandoff::STATUS_RUN_CLAIMED
                    && (int) $kitchenReturn->rider_id === $riderId;
                $isDispatchedKitchenReturn = $kitchenReturn?->status === KitchenWarehouseHandoff::STATUS_DISPATCHED
                    && (int) $kitchenReturn->rider_id === $riderId;
                $isInTransitKitchenReturn = $kitchenReturn?->status === KitchenWarehouseHandoff::STATUS_IN_TRANSIT
                    && (int) $box->held_by_user_id === $riderId;
                $enRouteToWarehouse = $isInTransitKitchenReturn
                    || in_array($latestAction, ['dispatched_to_warehouse', 'rider_accepted_warehouse_return'], true)
                        && (int) $box->held_by_user_id === $riderId
                        && ! $linkedOrder;

                $isAcceptedWarehouseStock = $latestAction === 'rider_accepted_kitchen_stock'
                    && (int) $box->held_by_user_id === $riderId
                    && $box->kitchen_id !== null
                    && ! $linkedOrder;

                $canAcceptPickup = $isStagedPickup;
                $canClaimKitchenReturn = (bool) $isClaimableKitchenReturn;
                $canAcceptKitchenReturn = (bool) $isDispatchedKitchenReturn;
                $canHandWarehouseStock = $isAcceptedWarehouseStock;
                $canHandToKitchen = ! $enRouteToWarehouse
                    && ! $isStagedPickup
                    && ! $kitchenReturn
                    && ! $isAcceptedWarehouseStock
                    && $box->kitchen_id === null
                    && $linkedOrder !== null
                    && $linkedOrder->orderGroup?->kitchen_id;

                $runLabel = 'With you';
                if ($isStagedPickup) {
                    $runLabel = 'Ready for pickup at warehouse';
                } elseif ($isClaimableKitchenReturn) {
                    $runLabel = 'Claim kitchen→ops run';
                } elseif ($isClaimedWaitingDispatch) {
                    $runLabel = 'Claimed — waiting kitchen dispatch';
                } elseif ($isDispatchedKitchenReturn) {
                    $runLabel = 'Dispatched — accept box at kitchen';
                } elseif ($enRouteToWarehouse || $isInTransitKitchenReturn) {
                    $runLabel = 'En route — hand to Middo ops';
                } elseif ($latestAction === 'handed_to_kitchen_stock' || $latestAction === 'returned_to_kitchen') {
                    $runLabel = 'Handed — awaiting kitchen receive';
                } elseif ($isAcceptedWarehouseStock) {
                    $runLabel = 'On the way to kitchen';
                } elseif ($linkedOrder && $linkedOrder->delivery_rider_id && $box->kitchen_id === null) {
                    $runLabel = 'Return to kitchen';
                } elseif ($box->kitchen_id !== null) {
                    $runLabel = 'On the way to kitchen';
                }

                $showWarehouseDestination = $enRouteToWarehouse
                    || $isClaimableKitchenReturn
                    || $isClaimedWaitingDispatch
                    || $isDispatchedKitchenReturn
                    || $isInTransitKitchenReturn;

                $opsKitchenRequestId = $stagedLink?->kitchen_box_request_id
                    ?? $requestLinksByBoxId->get($box->id)?->kitchen_box_request_id;

                $runGroupKey = $opsKitchenRequestId
                    ? 'ops-kitchen-'.$opsKitchenRequestId
                    : ($kitchenReturn
                        ? 'kitchen-ops-'.($kitchenReturn->kitchen_id ?? 'x').'-'.$kitchenReturn->status
                        : 'solo-'.$box->id);

                $runGroupTitle = $opsKitchenRequestId
                    ? 'Ops→kitchen run #'.$opsKitchenRequestId
                    : ($kitchenReturn
                        ? 'Kitchen→ops return'
                        : 'Single box');

                return [
                    'id' => $box->id,
                    'qr_code_id' => $box->qr_code_id,
                    'model' => str($box->box_model_type)->headline()->toString(),
                    'run_label' => $runLabel,
                    'run_group_key' => $runGroupKey,
                    'run_group_title' => $runGroupTitle,
                    'request_id' => $opsKitchenRequestId ? (int) $opsKitchenRequestId : null,
                    'kitchen_name' => $showWarehouseDestination
                        ? (($destinationKitchen?->name ? $destinationKitchen->name.' → ' : '').'Middo warehouse')
                        : $destinationKitchen?->name,
                    'kitchen_mobile' => $showWarehouseDestination && ! $destinationKitchen
                        ? null
                        : $destinationKitchen?->mobile,
                    'kitchen_address' => $showWarehouseDestination && ! $destinationKitchen
                        ? null
                        : $destinationKitchen?->address,
                    'order_id' => $linkedOrder?->id,
                    'menu_name' => $linkedOrder?->menuItem?->name,
                    'customer_name' => $linkedOrder
                        ? $linkedOrder->partyPayload()['customer_name']
                        : null,
                    'can_accept_pickup' => (bool) $canAcceptPickup,
                    'can_claim_kitchen_return' => $canClaimKitchenReturn,
                    'can_accept_kitchen_return' => $canAcceptKitchenReturn,
                    'can_hand_warehouse_stock' => (bool) $canHandWarehouseStock,
                    'can_hand_to_kitchen' => (bool) $canHandToKitchen,
                    'can_deliver_to_warehouse' => (bool) ($enRouteToWarehouse || $isInTransitKitchenReturn),
                ];
            })
            ->values();

        $runGroups = $nodes
            ->groupBy('run_group_key')
            ->map(function ($groupNodes, $key) {
                $first = $groupNodes->first();

                return [
                    'key' => $key,
                    'title' => $first['run_group_title'],
                    'request_id' => $first['request_id'],
                    'kitchen_name' => $first['kitchen_name'],
                    'box_count' => $groupNodes->count(),
                    'accept_all_ids' => $groupNodes
                        ->filter(fn (array $n) => $n['can_accept_pickup'])
                        ->pluck('id')
                        ->values()
                        ->all(),
                    'nodes' => $groupNodes->values()->all(),
                ];
            })
            ->values()
            ->all();

        return view('livewire.delivery.pending-box-runs', [
            'boxes' => $boxes,
            'nodes' => $nodes->all(),
            'runGroups' => $runGroups,
            'statusMessage' => $this->statusMessage,
            'errorMessage' => $this->errorMessage,
        ])->layout('delivery.layout.app', ['title' => 'Middo Boxes Pending Run']);
    }

    public function acceptAllWarehouseStock(array $boxIds = []): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $ids = collect($boxIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $accepted = 0;
        $errors = [];
        foreach ($ids as $boxId) {
            try {
                KitchenBoxRequestFlow::acceptCustody($boxId, (int) Auth::id());
                $accepted++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage() ?: 'Could not accept a box.';
            }
        }

        if ($accepted > 0) {
            $this->statusMessage = "Accepted {$accepted} ".str('box')->plural($accepted).' — deliver to kitchen, then mark handed.';
            $this->resetPage();
        }
        if ($errors !== []) {
            $this->errorMessage = $errors[0];
        }
    }

    public function acceptRunPickup(int $requestId): void
    {
        $ids = KitchenBoxRequestBox::query()
            ->where('kitchen_box_request_id', $requestId)
            ->where('rider_id', (int) Auth::id())
            ->where('status', KitchenBoxRequestBox::STATUS_READY_FOR_PICKUP)
            ->pluck('middo_box_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->acceptAllWarehouseStock($ids);
    }
}
