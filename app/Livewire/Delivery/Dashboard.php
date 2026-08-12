<?php

namespace App\Livewire\Delivery;

use App\Models\CustomRun;
use App\Models\Order;
use App\Support\DeliveryAreaScope;
use App\Support\RiderPendingBoxes;
use App\Support\RiderShift;
use App\Support\StaffAlerts;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public array $tiles = [];

    public string $shiftStatus = RiderShift::ON;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $rider = Auth::user();
        $this->shiftStatus = $rider->riderShiftStatus();
        $this->refreshTiles();
    }

    public function setShift(string $status): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        if (! RiderShift::isValid($status)) {
            $this->errorMessage = 'Invalid shift status.';

            return;
        }

        $rider = Auth::user();
        if (! $rider?->isDelivery()) {
            $this->errorMessage = 'Only delivery riders can set shift status.';

            return;
        }

        $rider->update(['rider_shift_status' => $status]);
        $this->shiftStatus = $status;
        $this->statusMessage = 'Shift set to '.RiderShift::label($status).'.';
        $this->refreshTiles();
    }

    protected function refreshTiles(): void
    {
        $rider = Auth::user();
        $riderId = (int) $rider->id;

        $this->tiles = [
            [
                'label' => 'Alerts',
                'count' => StaffAlerts::unreadCount((int) $riderId),
                'route' => 'delivery.alerts',
            ],
            [
                'label' => 'Kitchen dispatches',
                'count' => Order::query()
                    ->kitchenDispatched()
                    ->tap(fn ($q) => DeliveryAreaScope::applyKitchenDispatchedVisibleToRider($q, $rider))
                    ->count(),
                'route' => 'delivery.kitchen-dispatches',
            ],
            [
                'label' => 'Custom runs',
                'count' => CustomRun::query()
                    ->visibleToRider($rider)
                    ->whereIn('status', [CustomRun::STATUS_PENDING, CustomRun::STATUS_STARTED])
                    ->count(),
                'route' => 'delivery.custom-runs',
            ],
            [
                'label' => 'Middo boxes pending run',
                // Include warehouse stock staged for this rider (not yet in custody).
                'count' => RiderPendingBoxes::countForRider((int) $riderId),
                'route' => 'delivery.middo-boxes.pending-run',
            ],
            [
                'label' => 'Delivered orders',
                'count' => Order::query()->deliveredForRider($riderId)->count(),
                'route' => 'delivery.orders.delivered',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.delivery.dashboard', [
            'shiftLabel' => RiderShift::label($this->shiftStatus),
            'shiftOptions' => [
                RiderShift::ON => RiderShift::label(RiderShift::ON),
                RiderShift::OFF => RiderShift::label(RiderShift::OFF),
                RiderShift::UNABLE => RiderShift::label(RiderShift::UNABLE),
            ],
        ])->layout('delivery.layout.app', ['title' => 'Delivery Dashboard']);
    }
}
