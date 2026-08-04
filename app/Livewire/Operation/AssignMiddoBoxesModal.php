<?php

namespace App\Livewire\Operation;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoOperatingCosts;
use App\Support\RiderCommission;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class AssignMiddoBoxesModal extends Component
{
    public bool $showModal = false;

    /** @var int[] */
    public array $boxIds = [];

    public ?int $selectedRiderId = null;

    public ?int $selectedKitchenId = null;

    public array $riders = [];

    public array $kitchens = [];

    #[On('open-assign-middo-boxes-modal')]
    public function openModal($boxIds = []): void
    {
        $ids = is_array($boxIds) ? ($boxIds['boxIds'] ?? $boxIds) : [];

        $this->boxIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($this->boxIds === []) {
            return;
        }

        $this->resetErrorBag();
        $this->selectedRiderId = null;
        $this->selectedKitchenId = null;
        $this->riders = $this->fetchRiders();
        $this->kitchens = $this->fetchKitchens();
        $this->showModal = true;
    }

    public function updatedSelectedKitchenId($value): void
    {
        $this->riders = $this->fetchRiders(
            $value ? (int) $value : null
        );
        if ($this->selectedRiderId && ! collect($this->riders)->contains('id', $this->selectedRiderId)) {
            $this->selectedRiderId = null;
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->boxIds = [];
        $this->selectedRiderId = null;
        $this->selectedKitchenId = null;
        $this->riders = [];
        $this->kitchens = [];
    }

    public function save(): void
    {
        if ($this->boxIds === []) {
            return;
        }

        $this->validate([
            'selectedRiderId' => 'required|exists:users,id',
            'selectedKitchenId' => 'required|exists:users,id',
        ]);

        $assignedCount = DB::transaction(function () {
            $boxes = MiddoBox::query()
                ->whereIn('id', $this->boxIds)
                ->where('asset_status', 'at_middo_warehouse')
                ->lockForUpdate()
                ->get();

            if ($boxes->isEmpty()) {
                return 0;
            }

            $boxIds = $boxes->pluck('id');

            MiddoBox::query()
                ->whereIn('id', $boxIds)
                ->update([
                    'held_by_user_id' => $this->selectedRiderId,
                    'kitchen_id' => $this->selectedKitchenId,
                    'asset_status' => 'active',
                ]);

            foreach ($boxIds as $boxId) {
                MiddoBoxLog::create([
                    'middo_box_id' => $boxId,
                    'custody_status' => 'in_transit',
                    'log_action' => 'dispatched_to_kitchen',
                ]);
            }

            $rider = User::query()->find($this->selectedRiderId);
            if ($rider) {
                $perBox = RiderCommission::forSettingsRun($rider, DeliveryRunType::OPS_TO_KITCHEN);
                foreach ($boxIds as $boxId) {
                    $box = $boxes->firstWhere('id', $boxId);
                    MiddoOperatingCosts::bookRiderCommission(
                        $rider,
                        DeliveryRunType::OPS_TO_KITCHEN,
                        $perBox,
                        MiddoBox::class,
                        (int) $boxId,
                        'Ops→kitchen box #'.($box?->qr_code_id ?? $boxId),
                        $rider->id
                    );
                }
            }

            return $boxIds->count();
        });

        if ($assignedCount === 0) {
            $this->addError('selectedRiderId', 'No warehouse boxes were available to assign.');

            return;
        }

        $this->dispatch('middo-boxes-assigned');
        $this->closeModal();
    }

    protected function fetchRiders(?int $kitchenId = null): array
    {
        $kitchenAreaId = null;
        if ($kitchenId) {
            $kitchenAreaId = User::query()->whereKey($kitchenId)->value('area_id');
            $kitchenAreaId = $kitchenAreaId !== null ? (int) $kitchenAreaId : null;
        }

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

    protected function fetchKitchens(): array
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'kitchen'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.operation.assign-middo-boxes-modal');
    }
}
