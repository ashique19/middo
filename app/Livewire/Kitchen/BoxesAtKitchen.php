<?php

namespace App\Livewire\Kitchen;

use App\Models\KitchenBoxRequest;
use App\Models\KitchenBoxRequestLog;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\User;
use App\Support\KitchenBoxRequestFlow;
use App\Support\MiddoBoxKitchenActions;
use App\Support\MiddoSettings;
use App\Support\StaffAlerts;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class BoxesAtKitchen extends Component
{
    use WithPagination;

    public string $filter = 'inventory';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public ?int $damageBoxId = null;

    public string $damageNotes = '';

    public ?int $viaRiderBoxId = null;

    public ?int $selectedRiderId = null;

    public bool $showRequestModal = false;

    public string $requestQuantity = '1';

    public string $requestNote = '';

    public function mount(): void
    {
        if (request()->boolean('request')) {
            $this->openRequestModal();
        }
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
        $this->cancelDamage();
        $this->cancelViaRider();
        $this->closeRequestModal();
    }

    public function openRequestModal(): void
    {
        $this->errorMessage = null;
        $this->cancelDamage();
        $this->cancelViaRider();
        $this->showRequestModal = true;
        $this->requestQuantity = '1';
        $this->requestNote = '';
        $this->resetErrorBag();
    }

    public function closeRequestModal(): void
    {
        $this->showRequestModal = false;
        $this->requestQuantity = '1';
        $this->requestNote = '';
        $this->resetErrorBag();
    }

    public function submitBoxRequest(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $this->validate([
                'requestQuantity' => 'required|integer|min:1|max:500',
                'requestNote' => 'nullable|string|max:1000',
            ]);

            $kitchen = Auth::user();
            if (! $kitchen) {
                throw new \RuntimeException('You must be logged in.');
            }

            $request = KitchenBoxRequest::create([
                'kitchen_id' => $kitchen->id,
                'quantity' => (int) $this->requestQuantity,
                'allocated_qty' => 0,
                'status' => KitchenBoxRequest::STATUS_PENDING,
                'note' => trim($this->requestNote) !== '' ? trim($this->requestNote) : null,
                'requested_by' => $kitchen->id,
            ]);

            KitchenBoxRequestFlow::logRequestEvent(
                $request,
                KitchenBoxRequestLog::EVENT_REQUESTED,
                $kitchen->id,
                $request->note,
                ['quantity' => (int) $request->quantity]
            );

            StaffAlerts::notifyOpsKitchenBoxRequest($request);

            $this->statusMessage = "Requested {$request->quantity} Middo ".str('box')->plural($request->quantity).'. Ops can see this on Middo Boxes.';
            $this->closeRequestModal();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not submit box request.';
        }
    }

    public function cancelBoxRequest(int $requestId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $request = KitchenBoxRequest::query()
            ->whereKey($requestId)
            ->where('kitchen_id', Auth::id())
            ->where('status', KitchenBoxRequest::STATUS_PENDING)
            ->first();

        if (! $request) {
            $this->errorMessage = 'That box request is no longer pending.';

            return;
        }

        try {
            KitchenBoxRequestFlow::cancelRequest($request, (int) Auth::id());
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = 'Box request cancelled.';
    }

    public function openDamage(int $boxId): void
    {
        $this->errorMessage = null;
        $this->cancelViaRider();
        $this->damageBoxId = $boxId;
        $this->damageNotes = '';
    }

    public function cancelDamage(): void
    {
        $this->damageBoxId = null;
        $this->damageNotes = '';
    }

    public function openViaRider(int $boxId): void
    {
        if (! MiddoSettings::kitchenToOpsViaRider()) {
            return;
        }

        $this->errorMessage = null;
        $this->cancelDamage();

        $box = MiddoBox::query()->with('warehouseHandoff')->find($boxId);
        if ($box?->hasOpenWarehouseHandoff()) {
            $this->errorMessage = 'This box is already tagged for rider pickup to Middo warehouse.';

            return;
        }

        $this->viaRiderBoxId = $boxId;
        $this->selectedRiderId = null;
    }

    public function cancelViaRider(): void
    {
        $this->viaRiderBoxId = null;
        $this->selectedRiderId = null;
    }

    public function confirmDamage(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        if (! $this->damageBoxId) {
            return;
        }

        try {
            $this->validate([
                'damageNotes' => 'nullable|string|max:1000',
            ]);

            $box = MiddoBox::query()->findOrFail($this->damageBoxId);
            $damaged = MiddoBoxKitchenActions::markDamaged($box, (int) Auth::id(), $this->damageNotes);
            $this->statusMessage = "{$damaged->qr_code_id} marked damaged. Send it to Middo on the damaged path — not as a normal return.";
            $this->cancelDamage();
            $this->filter = 'damaged';
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not mark box damaged.';
        }
    }

    public function sendToWarehouse(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $box = MiddoBox::query()->findOrFail($boxId);
            $sent = MiddoBoxKitchenActions::sendToWarehouse($box, (int) Auth::id());
            $this->statusMessage = "{$sent->qr_code_id} sent to Middo warehouse.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not send box to warehouse.';
        }
    }

    public function sendViaRider(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        if (! MiddoSettings::kitchenToOpsViaRider()) {
            $this->errorMessage = 'Kitchen→ops via rider is not enabled in Settings.';

            return;
        }

        if (! $this->viaRiderBoxId) {
            return;
        }

        try {
            $this->validate([
                'selectedRiderId' => 'required|exists:users,id',
            ]);

            $box = MiddoBox::query()->findOrFail($this->viaRiderBoxId);
            $tagged = MiddoBoxKitchenActions::stageForWarehousePickup(
                $box,
                (int) Auth::id(),
                (int) $this->selectedRiderId
            );
            $riderName = $tagged->warehouseHandoff?->rider?->name ?? 'rider';
            $this->statusMessage = "{$tagged->qr_code_id} tagged for {$riderName}. Keep it at kitchen until they accept custody.";
            $this->cancelViaRider();
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not tag rider.';
        }
    }

    public function sendDamagedToWarehouse(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        try {
            $box = MiddoBox::query()->findOrFail($boxId);
            $sent = MiddoBoxKitchenActions::sendDamagedToWarehouse($box, (int) Auth::id());
            $this->statusMessage = "{$sent->qr_code_id} sent to Middo as damaged. Ops will review — not restocked as normal inventory.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not send damaged box.';
        }
    }

    /**
     * Kitchen→ops box runs are not customer deliveries: show every active rider
     * (same policy as ops→kitchen staging). Area-matched riders are listed first.
     *
     * @return list<array{id: int, name: string, areas_label: string}>
     */
    protected function fetchRidersForKitchen(): array
    {
        $kitchen = Auth::user();
        $kitchenAreaId = $kitchen?->area_id !== null ? (int) $kitchen->area_id : null;

        return User::query()
            ->with(['role', 'areas', 'area'])
            ->whereHas('role', fn ($query) => $query->where('name', 'delivery'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->sortBy(function (User $user) use ($kitchenAreaId) {
                if ($kitchenAreaId === null) {
                    return 1;
                }

                return $user->servesArea($kitchenAreaId) ? 0 : 1;
            })
            ->values()
            ->map(function (User $user) {
                $names = $user->areas->pluck('name')->filter()->sort()->values()->all();
                if ($names === [] && $user->area?->name) {
                    $names = [$user->area->name];
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'areas_label' => $names === [] ? 'No coverage areas' : implode(', ', $names),
                ];
            })
            ->all();
    }

    public function render()
    {
        $kitchenId = (int) Auth::id();
        $viaRiderEnabled = MiddoSettings::kitchenToOpsViaRider();

        $counts = [
            'inventory' => MiddoBox::query()->atKitchen($kitchenId)->where('asset_status', '!=', 'damaged')->count(),
            'sendable' => MiddoBox::query()->sendableAtKitchen($kitchenId)->count(),
            'damaged' => MiddoBox::query()->damagedAtKitchen($kitchenId)->count(),
            'history' => MiddoBoxLog::query()
                ->where('performed_by', $kitchenId)
                ->whereIn('log_action', [
                    'returned_to_warehouse',
                    'dispatched_to_warehouse',
                    'staged_for_warehouse_pickup',
                    'returned_damaged_to_warehouse',
                    'marked_damaged_at_kitchen',
                    'received_at_kitchen',
                ])
                ->count(),
        ];

        $pendingRequests = KitchenBoxRequest::query()
            ->where('kitchen_id', $kitchenId)
            ->pending()
            ->latest('id')
            ->get();

        if ($this->filter === 'history') {
            $history = MiddoBoxLog::query()
                ->with(['middoBox', 'performedBy'])
                ->where('performed_by', $kitchenId)
                ->whereIn('log_action', [
                    'returned_to_warehouse',
                    'dispatched_to_warehouse',
                    'staged_for_warehouse_pickup',
                    'returned_damaged_to_warehouse',
                    'marked_damaged_at_kitchen',
                    'received_at_kitchen',
                ])
                ->latest('id')
                ->paginate(20);

            return view('livewire.kitchen.boxes-at-kitchen', [
                'boxes' => null,
                'history' => $history,
                'counts' => $counts,
                'viaRiderEnabled' => $viaRiderEnabled,
                'riders' => [],
                'pendingRequests' => $pendingRequests,
            ])->layout('kitchen.layout.app', ['title' => 'Boxes at Kitchen']);
        }

        $boxesQuery = MiddoBox::query()
            ->atKitchen($kitchenId)
            ->with(['warehouseHandoff.rider'])
            ->withCount('orderMiddoBoxes');

        $boxesQuery = match ($this->filter) {
            'sendable' => $boxesQuery->where('asset_status', '!=', 'damaged')->whereDoesntHave('orderMiddoBoxes'),
            'damaged' => $boxesQuery->where('asset_status', 'damaged'),
            default => $boxesQuery->where('asset_status', '!=', 'damaged'),
        };

        $boxes = $boxesQuery->orderBy('qr_code_id')->paginate(20);

        return view('livewire.kitchen.boxes-at-kitchen', [
            'boxes' => $boxes,
            'history' => null,
            'counts' => $counts,
            'viaRiderEnabled' => $viaRiderEnabled,
            'riders' => $viaRiderEnabled ? $this->fetchRidersForKitchen() : [],
            'pendingRequests' => $pendingRequests,
        ])->layout('kitchen.layout.app', ['title' => 'Boxes at Kitchen']);
    }
}
