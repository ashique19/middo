<?php

namespace App\Livewire\Operation;

use App\Models\KitchenBoxRequest;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Support\KitchenBoxRequestFlow;
use App\Support\OpsBoxCustody;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MiddoBoxes extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    /** all|returns|warehouse|to_kitchen */
    public string $custodyFilter = 'all';

    /** @var int[] */
    public array $selectedBoxIds = [];

    public ?int $selectedRequestId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public ?string $warningMessage = null;

    public ?int $closingRequestId = null;

    public string $closeNote = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->clearBoxSelection();
        // Status dropdown and custody tiles are mutually exclusive filters.
        if ($this->custodyFilter !== 'all' && $this->custodyFilter !== 'returns') {
            $this->custodyFilter = 'all';
        }
    }

    public function updatingCustodyFilter(): void
    {
        $this->resetPage();
        $this->clearBoxSelection();
    }

    public function toggleCustodyFilter(string $key): void
    {
        $allowed = ['warehouse', 'to_kitchen', 'returns'];
        if (! in_array($key, $allowed, true)) {
            return;
        }

        $this->statusFilter = '';
        $this->custodyFilter = $this->custodyFilter === $key ? 'all' : $key;
        $this->resetPage();
        $this->clearBoxSelection();
    }

    #[On('middo-boxes-generated')]
    public function refreshBoxes(?int $count = null): void
    {
        $this->resetPage();
        $this->clearBoxSelection();
        if ($count !== null && $count > 0) {
            $this->statusMessage = "Generated {$count} new Middo ".str('box')->plural($count).' (latest first on this list).';
        }
    }

    #[On('middo-boxes-assigned')]
    public function clearSelection(): void
    {
        $this->clearBoxSelection();
        $this->statusMessage = 'Boxes marked ready for rider pickup against the kitchen request.';
    }

    protected function clearBoxSelection(): void
    {
        $this->selectedBoxIds = [];
        $this->selectedRequestId = null;
        $this->warningMessage = null;
    }

    public function openAssignModal(): void
    {
        if ($this->selectedBoxIds === []) {
            return;
        }

        // Drop any selection that became unavailable (e.g. staged in another tab).
        $availableIds = MiddoBox::query()
            ->availableForKitchenStaging()
            ->whereIn('id', $this->selectedBoxIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->selectedBoxIds = $availableIds;

        if ($this->selectedBoxIds === []) {
            $this->selectedRequestId = null;
            $this->errorMessage = 'Selected boxes are no longer free warehouse stock. Pick boxes that are not already staged.';

            return;
        }

        $kitchenId = null;
        if ($this->selectedRequestId) {
            $kitchenId = KitchenBoxRequest::query()
                ->whereKey($this->selectedRequestId)
                ->value('kitchen_id');
            $kitchenId = $kitchenId !== null ? (int) $kitchenId : null;
        }

        $this->errorMessage = null;
        $this->dispatch(
            'open-assign-middo-boxes-modal',
            boxIds: $this->selectedBoxIds,
            kitchenId: $kitchenId,
        );
    }

    public function toggleBoxSelection(int $boxId): void
    {
        $this->selectedRequestId = null;
        $this->warningMessage = null;

        if (in_array($boxId, $this->selectedBoxIds, true)) {
            $this->selectedBoxIds = array_values(array_filter(
                $this->selectedBoxIds,
                fn (int $id) => $id !== $boxId
            ));

            return;
        }

        $box = MiddoBox::query()->with('requestBox')->find($boxId);
        if (! $box || ! $box->isAvailableForKitchenStaging()) {
            $this->errorMessage = 'Only free warehouse boxes can be staged. Already staged boxes stay reserved for their rider.';

            return;
        }

        $this->selectedBoxIds[] = $boxId;
        $this->errorMessage = null;
    }

    /**
     * Checkbox on a kitchen box request: select (or clear) warehouse boxes
     * for that request's remaining quantity (latest free stock first).
     */
    public function toggleRequestBoxSelection(int $requestId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $this->warningMessage = null;

        if ($this->selectedRequestId === $requestId) {
            $this->clearBoxSelection();

            return;
        }

        $request = KitchenBoxRequest::query()
            ->with('kitchen')
            ->open()
            ->whereKey($requestId)
            ->first();

        if (! $request) {
            $this->errorMessage = 'That box request is no longer open.';

            return;
        }

        $needed = $request->remainingQuantity();
        if ($needed < 1) {
            $this->selectedRequestId = null;
            $this->selectedBoxIds = [];
            $this->warningMessage = 'This request has no remaining boxes to stage.';

            return;
        }

        $availableIds = MiddoBox::query()
            ->availableForKitchenStaging()
            ->orderByDesc('id')
            ->limit($needed)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $availableCount = count($availableIds);
        $kitchenName = $request->kitchen?->name ?? ('Kitchen #'.$request->kitchen_id);

        if ($availableCount === 0) {
            $this->selectedRequestId = null;
            $this->selectedBoxIds = [];
            $this->warningMessage = "Insufficient warehouse boxes for {$kitchenName}. Need {$needed}, have 0 available.";

            return;
        }

        $this->selectedRequestId = $requestId;
        $this->selectedBoxIds = $availableIds;

        if ($availableCount < $needed) {
            $this->warningMessage = "Insufficient warehouse boxes for {$kitchenName}. Need {$needed}, selected {$availableCount} available.";
        }
    }

    public function retire(int $boxId): void
    {
        $box = MiddoBox::query()->find($boxId);
        if (! $box || $box->asset_status === 'retired') {
            return;
        }

        $box->update(['asset_status' => 'retired']);
        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'warehouse',
            'log_action' => 'retired_at_warehouse',
            'performed_by' => Auth::id(),
        ]);

        $this->selectedBoxIds = array_values(array_filter(
            $this->selectedBoxIds,
            fn (int $id) => $id !== $boxId
        ));
    }

    public function reactivate(int $boxId): void
    {
        $box = MiddoBox::query()
            ->whereKey($boxId)
            ->whereIn('asset_status', ['damaged', 'retired', 'maintenance', 'lost'])
            ->first();

        if (! $box) {
            return;
        }

        $box->update([
            'asset_status' => 'at_middo_warehouse',
            'kitchen_id' => null,
            'held_by_user_id' => null,
        ]);

        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'warehouse',
            'log_action' => 'reactivated_at_warehouse',
            'performed_by' => Auth::id(),
        ]);
    }

    public function ackReturn(int $boxId): void
    {
        $box = MiddoBox::query()->find($boxId);
        if (! $box) {
            return;
        }

        OpsBoxCustody::ackReturn($box, Auth::id());
        $this->statusMessage = "Acknowledged return for {$box->qr_code_id}.";
        $this->selectedBoxIds = array_values(array_filter(
            $this->selectedBoxIds,
            fn (int $id) => $id !== $boxId
        ));
    }

    public function openCloseRequest(int $requestId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $this->closingRequestId = $requestId;
        $this->closeNote = '';
        $this->resetErrorBag();
    }

    public function cancelCloseRequest(): void
    {
        $this->closingRequestId = null;
        $this->closeNote = '';
        $this->resetErrorBag();
    }

    public function closeBoxRequest(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $this->validate([
            'closingRequestId' => 'required|integer',
            'closeNote' => 'required|string|min:3|max:2000',
        ]);

        $request = KitchenBoxRequest::query()
            ->with('kitchen')
            ->open()
            ->whereKey($this->closingRequestId)
            ->first();

        if (! $request) {
            $this->errorMessage = 'That box request is no longer open.';
            $this->cancelCloseRequest();

            return;
        }

        try {
            KitchenBoxRequestFlow::closeRequest(
                $request,
                (int) Auth::id(),
                $this->closeNote
            );
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $kitchenName = $request->kitchen?->name ?? ('Kitchen #'.$request->kitchen_id);
        $this->statusMessage = "Closed box request #{$request->id} for {$kitchenName}.";
        $this->cancelCloseRequest();
    }

    public function cancelBoxRequest(int $requestId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $request = KitchenBoxRequest::query()
            ->open()
            ->whereKey($requestId)
            ->first();

        if (! $request) {
            $this->errorMessage = 'That box request is no longer open.';

            return;
        }

        try {
            KitchenBoxRequestFlow::cancelRequest($request, (int) Auth::id());
        } catch (\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = "Cancelled box request #{$request->id}.";
        if ($this->closingRequestId === $requestId) {
            $this->cancelCloseRequest();
        }
        if ($this->selectedRequestId === $requestId) {
            $this->clearBoxSelection();
        }
    }

    public function render()
    {
        $summary = OpsBoxCustody::summary();

        $relations = ['heldByUser', 'requestBox.rider', 'requestBox.request.kitchen'];

        $query = match ($this->custodyFilter) {
            'returns' => OpsBoxCustody::returnsQuery()->with($relations),
            'warehouse' => OpsBoxCustody::warehouseFreeQuery()->with($relations),
            'to_kitchen' => OpsBoxCustody::toKitchenQuery()->with($relations),
            default => MiddoBox::query()->with($relations),
        };

        $boxes = $query
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('qr_code_id', 'like', '%'.$this->search.'%')
                        ->orWhere('box_model_type', 'like', '%'.$this->search.'%')
                        ->orWhere('asset_status', 'like', '%'.$this->search.'%');
                });
            })
            ->when(
                $this->statusFilter !== '' && $this->custodyFilter === 'all',
                function ($q) {
                    // Dropdown "Warehouse" = free stock (same as Warehouse tile), not staged pickup.
                    if ($this->statusFilter === 'at_middo_warehouse') {
                        $q->availableForKitchenStaging();
                    } else {
                        $q->where('asset_status', $this->statusFilter);
                    }
                }
            )
            ->orderByRaw("CASE WHEN asset_status = 'damaged' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(20);

        $openBoxRequests = KitchenBoxRequest::query()
            ->with(['kitchen', 'requestedBy', 'requestBoxes'])
            ->open()
            ->latest('id')
            ->get();

        return view('livewire.operation.middo-boxes', [
            'boxes' => $boxes,
            'damagedCount' => $summary['damaged'],
            'custody' => $summary,
            'pendingBoxRequests' => $openBoxRequests,
        ])->layout('layouts.private.app', ['title' => 'Middo Boxes']);
    }
}
