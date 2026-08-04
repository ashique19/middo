<?php

namespace App\Livewire\Kitchen;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Support\MiddoBoxKitchenActions;
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

    public function updatingFilter(): void
    {
        $this->resetPage();
        $this->cancelDamage();
    }

    public function openDamage(int $boxId): void
    {
        $this->errorMessage = null;
        $this->damageBoxId = $boxId;
        $this->damageNotes = '';
    }

    public function cancelDamage(): void
    {
        $this->damageBoxId = null;
        $this->damageNotes = '';
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

    public function render()
    {
        $kitchenId = (int) Auth::id();

        $counts = [
            'inventory' => MiddoBox::query()->atKitchen($kitchenId)->where('asset_status', '!=', 'damaged')->count(),
            'sendable' => MiddoBox::query()->sendableAtKitchen($kitchenId)->count(),
            'damaged' => MiddoBox::query()->damagedAtKitchen($kitchenId)->count(),
            'history' => MiddoBoxLog::query()
                ->where('performed_by', $kitchenId)
                ->whereIn('log_action', [
                    'returned_to_warehouse',
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
        ])->layout('kitchen.layout.app', ['title' => 'Boxes at Kitchen']);
    }
}
