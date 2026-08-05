<?php

namespace App\Livewire\Kitchen;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\User;
use App\Support\MiddoBoxKitchenActions;
use App\Support\MiddoSettings;
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

    public function updatingFilter(): void
    {
        $this->resetPage();
        $this->cancelDamage();
        $this->cancelViaRider();
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
            $sent = MiddoBoxKitchenActions::dispatchToWarehouseViaRider(
                $box,
                (int) Auth::id(),
                (int) $this->selectedRiderId
            );
            $this->statusMessage = "{$sent->qr_code_id} handed to rider for Middo warehouse.";
            $this->cancelViaRider();
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not assign rider.';
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
     * @return list<array{id: int, name: string}>
     */
    protected function fetchRidersForKitchen(): array
    {
        $kitchen = Auth::user();
        $kitchenAreaId = $kitchen?->area_id !== null ? (int) $kitchen->area_id : null;

        return User::query()
            ->with(['role', 'areas'])
            ->whereHas('role', fn ($query) => $query->where('name', 'delivery'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->when(
                $kitchenAreaId !== null,
                fn ($riders) => $riders->filter(fn (User $user) => $user->servesArea($kitchenAreaId))
            )
            ->values()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
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
                    'returned_damaged_to_warehouse',
                    'marked_damaged_at_kitchen',
                    'received_at_kitchen',
                ])
                ->count(),
        ];

        if ($this->filter === 'history') {
            $history = MiddoBoxLog::query()
                ->with(['middoBox', 'performedBy'])
                ->where('performed_by', $kitchenId)
                ->whereIn('log_action', [
                    'returned_to_warehouse',
                    'dispatched_to_warehouse',
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
            ])->layout('kitchen.layout.app', ['title' => 'Boxes at Kitchen']);
        }

        $boxesQuery = MiddoBox::query()->atKitchen($kitchenId)->withCount('orderMiddoBoxes');

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
        ])->layout('kitchen.layout.app', ['title' => 'Boxes at Kitchen']);
    }
}
