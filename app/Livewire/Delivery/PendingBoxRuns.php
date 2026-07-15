<?php

namespace App\Livewire\Delivery;

use App\Models\MiddoBox;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PendingBoxRuns extends Component
{
    use WithPagination;

    public function render()
    {
        $riderId = Auth::id();

        $boxes = MiddoBox::query()
            ->with(['kitchen', 'orderMiddoBoxes.order.menuItem', 'orderMiddoBoxes.order.user'])
            ->where('held_by_user_id', $riderId)
            ->where('asset_status', 'active')
            ->orderBy('qr_code_id')
            ->paginate(20);

        $nodes = collect($boxes->items())
            ->map(function (MiddoBox $box) {
                $linkedOrder = $box->orderMiddoBoxes->first()?->order;

                $runLabel = 'On the way to kitchen';
                if ($linkedOrder && $linkedOrder->delivery_rider_id) {
                    $runLabel = 'Kitchen → consumer';
                } elseif ($box->isIncomingToKitchen()) {
                    $runLabel = 'On the way to kitchen';
                }

                return [
                    'id' => $box->id,
                    'qr_code_id' => $box->qr_code_id,
                    'model' => str($box->box_model_type)->headline()->toString(),
                    'run_label' => $runLabel,
                    'kitchen_name' => $box->kitchen?->name,
                    'order_id' => $linkedOrder?->id,
                    'menu_name' => $linkedOrder?->menuItem?->name,
                    'customer_name' => $linkedOrder
                        ? (trim(($linkedOrder->user?->first_name ?? '').' '.($linkedOrder->user?->last_name ?? '')) ?: null)
                        : null,
                ];
            })
            ->all();

        return view('livewire.delivery.pending-box-runs', [
            'boxes' => $boxes,
            'nodes' => $nodes,
        ])->layout('layouts.private.app', ['title' => 'Middo Boxes Pending Run']);
    }
}
