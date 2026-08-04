<?php

namespace App\Livewire\Operation;

use App\Models\MiddoBox;
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

    /** all|returns */
    public string $custodyFilter = 'all';

    /** @var int[] */
    public array $selectedBoxIds = [];

    public ?string $statusMessage = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedBoxIds = [];
    }

    public function updatingCustodyFilter(): void
    {
        $this->resetPage();
        $this->selectedBoxIds = [];
    }

    #[On('middo-boxes-generated')]
    public function refreshBoxes(): void
    {
        $this->resetPage();
        $this->selectedBoxIds = [];
    }

    #[On('middo-boxes-assigned')]
    public function clearSelection(): void
    {
        $this->selectedBoxIds = [];
    }

    public function openAssignModal(): void
    {
        if ($this->selectedBoxIds === []) {
            return;
        }

        $this->dispatch('open-assign-middo-boxes-modal', boxIds: $this->selectedBoxIds);
    }

    public function toggleBoxSelection(int $boxId): void
    {
        if (in_array($boxId, $this->selectedBoxIds, true)) {
            $this->selectedBoxIds = array_values(array_filter(
                $this->selectedBoxIds,
                fn (int $id) => $id !== $boxId
            ));
        } else {
            $this->selectedBoxIds[] = $boxId;
        }
    }

    public function retire(int $boxId): void
    {
        MiddoBox::query()
            ->whereKey($boxId)
            ->update(['asset_status' => 'retired']);

        $this->selectedBoxIds = array_values(array_filter(
            $this->selectedBoxIds,
            fn (int $id) => $id !== $boxId
        ));
    }

    public function reactivate(int $boxId): void
    {
        MiddoBox::query()
            ->whereKey($boxId)
            ->whereIn('asset_status', ['damaged', 'retired', 'maintenance', 'lost'])
            ->update([
                'asset_status' => 'at_middo_warehouse',
                'kitchen_id' => null,
                'held_by_user_id' => null,
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

    public function render()
    {
        $summary = OpsBoxCustody::summary();

        $query = MiddoBox::query()->with('heldByUser');

        if ($this->custodyFilter === 'returns') {
            $query = OpsBoxCustody::returnsQuery()->with('heldByUser');
        }

        $boxes = $query
            ->when($this->search !== '', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('qr_code_id', 'like', '%'.$this->search.'%')
                        ->orWhere('box_model_type', 'like', '%'.$this->search.'%')
                        ->orWhere('asset_status', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('asset_status', $this->statusFilter))
            ->orderByRaw("CASE WHEN asset_status = 'damaged' THEN 0 ELSE 1 END")
            ->orderBy('qr_code_id')
            ->paginate(20);

        return view('livewire.operation.middo-boxes', [
            'boxes' => $boxes,
            'damagedCount' => $summary['damaged'],
            'custody' => $summary,
        ])->layout('layouts.private.app', ['title' => 'Middo Boxes']);
    }
}
