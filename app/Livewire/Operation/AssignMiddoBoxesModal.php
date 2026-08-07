<?php

namespace App\Livewire\Operation;

use App\Models\Area;
use App\Models\KitchenBoxRequest;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoOperatingCosts;
use App\Support\RiderCommission;
use App\Support\StaffAlerts;
use Illuminate\Support\Facades\Auth;
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

    public int $selectedKitchenPendingQty = 0;

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
        $this->selectedKitchenPendingQty = 0;
        $this->riders = $this->fetchRiders();
        $this->kitchens = $this->fetchKitchensWithPendingRequests();
        $this->showModal = true;
    }

    public function updatedSelectedKitchenId($value): void
    {
        $kitchenId = $value ? (int) $value : null;
        $this->selectedKitchenPendingQty = $kitchenId
            ? KitchenBoxRequest::pendingQuantityForKitchen($kitchenId)
            : 0;
        $this->riders = $this->fetchRiders($kitchenId);
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
        $this->selectedKitchenPendingQty = 0;
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

        $assignedCount = 0;
        $assignedBoxes = collect();
        $rider = null;
        $kitchen = null;

        try {
            DB::transaction(function () use (&$assignedCount, &$assignedBoxes, &$rider, &$kitchen) {
                $boxes = MiddoBox::query()
                    ->whereIn('id', $this->boxIds)
                    ->where('asset_status', 'at_middo_warehouse')
                    ->lockForUpdate()
                    ->get();

                if ($boxes->isEmpty()) {
                    return;
                }

                $boxIds = $boxes->pluck('id');
                $shipQty = $boxIds->count();

                KitchenBoxRequest::consumePendingForKitchen(
                    (int) $this->selectedKitchenId,
                    $shipQty,
                    Auth::id() ? (int) Auth::id() : null
                );

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
                $kitchen = User::query()->find($this->selectedKitchenId);
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

                $assignedBoxes = $boxes;
                $assignedCount = $shipQty;
            });
        } catch (\RuntimeException $e) {
            $this->addError('selectedKitchenId', $e->getMessage());

            return;
        }

        if ($assignedCount === 0) {
            $this->addError('selectedRiderId', 'No warehouse boxes were available to assign.');

            return;
        }

        if ($rider && $kitchen) {
            StaffAlerts::notifyOpsToKitchenBoxes($rider, $kitchen, $assignedBoxes);
        }

        $this->dispatch('middo-boxes-assigned');
        $this->closeModal();
    }

    protected function fetchRiders(?int $kitchenId = null): array
    {
        // Ops→kitchen box runs are not customer deliveries: show every active
        // rider. Area-matched riders are listed first when a kitchen is chosen.
        $kitchenAreaId = null;
        if ($kitchenId) {
            $kitchenAreaId = User::query()->whereKey($kitchenId)->value('area_id');
            $kitchenAreaId = $kitchenAreaId !== null ? (int) $kitchenAreaId : null;
        }

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
                $areaNames = $this->coverageAreaNames($user);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'areas_label' => $areaNames === [] ? 'No coverage areas' : implode(', ', $areaNames),
                    'search' => strtolower(trim($user->name.' '.implode(' ', $areaNames))),
                ];
            })
            ->all();
    }

    /**
     * Only kitchens with a pending box request can receive warehouse boxes.
     */
    protected function fetchKitchensWithPendingRequests(): array
    {
        $pendingByKitchen = KitchenBoxRequest::query()
            ->pending()
            ->selectRaw('kitchen_id, SUM(quantity) as pending_qty')
            ->groupBy('kitchen_id')
            ->pluck('pending_qty', 'kitchen_id');

        if ($pendingByKitchen->isEmpty()) {
            return [];
        }

        return User::query()
            ->with(['area', 'city'])
            ->whereHas('role', fn ($query) => $query->where('name', 'kitchen'))
            ->where('status', 'active')
            ->whereIn('id', $pendingByKitchen->keys())
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (User $user) use ($pendingByKitchen) {
                $area = trim((string) ($user->area?->name ?? ''));
                $city = trim((string) ($user->city?->name ?? ''));
                $location = collect([$area, $city])->filter()->implode(', ');
                $pendingQty = (int) ($pendingByKitchen[$user->id] ?? 0);
                $requestLabel = 'Requested '.$pendingQty.' '.str('box')->plural($pendingQty);
                $subtitle = collect([$requestLabel, $location !== '' ? $location : null])->filter()->implode(' · ');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'subtitle' => $subtitle,
                    'pending_qty' => $pendingQty,
                    'search' => strtolower(trim($user->name.' '.$location.' '.$requestLabel)),
                ];
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function coverageAreaNames(User $user): array
    {
        $ids = $user->serviceAreaIds();
        if ($ids === []) {
            return [];
        }

        if ($user->relationLoaded('areas') && $user->areas->isNotEmpty()) {
            $names = $user->areas
                ->whereIn('id', $ids)
                ->sortBy('name')
                ->pluck('name')
                ->map(fn ($name) => (string) $name)
                ->values()
                ->all();

            if ($names !== []) {
                return $names;
            }
        }

        return Area::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    public function render()
    {
        return view('livewire.operation.assign-middo-boxes-modal');
    }
}
