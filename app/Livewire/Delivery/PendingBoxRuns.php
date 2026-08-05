<?php

namespace App\Livewire\Delivery;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Support\MiddoBoxKitchenActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PendingBoxRuns extends Component
{
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function handToKitchen(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $riderId = (int) Auth::id();

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
            $delivered = MiddoBoxKitchenActions::deliverToWarehouseByRider($box, (int) Auth::id());
            $this->statusMessage = "{$delivered->qr_code_id} delivered to Middo warehouse.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not deliver box to warehouse.';
        }
    }

    public function render()
    {
        $riderId = Auth::id();

        $boxes = MiddoBox::query()
            ->with([
                'kitchen',
                'orderMiddoBoxes.order.menuItem',
                'orderMiddoBoxes.order.user',
                'orderMiddoBoxes.order.orderGroup.kitchen',
            ])
            ->where('held_by_user_id', $riderId)
            ->where('asset_status', 'active')
            ->orderBy('qr_code_id')
            ->paginate(20);

        $latestActions = MiddoBoxLog::query()
            ->whereIn('middo_box_id', collect($boxes->items())->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('middo_box_id')
            ->keyBy('middo_box_id');

        $nodes = collect($boxes->items())
            ->map(function (MiddoBox $box) use ($latestActions) {
                $linkedOrder = $box->orderMiddoBoxes->first()?->order;
                $destinationKitchen = $box->kitchen
                    ?? $linkedOrder?->orderGroup?->kitchen;
                $latestAction = $latestActions->get($box->id)?->log_action;
                $enRouteToWarehouse = $latestAction === 'dispatched_to_warehouse';

                $canHandToKitchen = ! $enRouteToWarehouse
                    && $box->kitchen_id === null
                    && $linkedOrder !== null
                    && $linkedOrder->orderGroup?->kitchen_id;

                $runLabel = 'With you';
                if ($enRouteToWarehouse) {
                    $runLabel = 'Return to Middo warehouse';
                } elseif ($box->isIncomingToKitchen()) {
                    $runLabel = 'Handed — awaiting kitchen receive';
                } elseif ($linkedOrder && $linkedOrder->delivery_rider_id && $box->kitchen_id === null) {
                    $runLabel = 'Return to kitchen';
                } elseif ($box->kitchen_id !== null) {
                    $runLabel = 'On the way to kitchen';
                }

                return [
                    'id' => $box->id,
                    'qr_code_id' => $box->qr_code_id,
                    'model' => str($box->box_model_type)->headline()->toString(),
                    'run_label' => $runLabel,
                    'kitchen_name' => $enRouteToWarehouse ? 'Middo warehouse' : $destinationKitchen?->name,
                    'kitchen_mobile' => $enRouteToWarehouse ? null : $destinationKitchen?->mobile,
                    'kitchen_address' => $enRouteToWarehouse ? null : $destinationKitchen?->address,
                    'order_id' => $linkedOrder?->id,
                    'menu_name' => $linkedOrder?->menuItem?->name,
                    'customer_name' => $linkedOrder
                        ? $linkedOrder->partyPayload()['customer_name']
                        : null,
                    'can_hand_to_kitchen' => (bool) $canHandToKitchen,
                    'can_deliver_to_warehouse' => $enRouteToWarehouse,
                ];
            })
            ->all();

        return view('livewire.delivery.pending-box-runs', [
            'boxes' => $boxes,
            'nodes' => $nodes,
            'statusMessage' => $this->statusMessage,
            'errorMessage' => $this->errorMessage,
        ])->layout('delivery.layout.app', ['title' => 'Middo Boxes Pending Run']);
    }
}
