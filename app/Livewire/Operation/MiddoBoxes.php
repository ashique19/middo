<?php

namespace App\Livewire\Operation;

use App\Models\MiddoBox;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MiddoBoxes extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    /** @var int[] */
    public array $selectedBoxIds = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
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

    public function render()
    {
        $boxes = MiddoBox::query()
            ->with('heldByUser')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('qr_code_id', 'like', '%'.$this->search.'%')
                        ->orWhere('box_model_type', 'like', '%'.$this->search.'%')
                        ->orWhere('asset_status', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('asset_status', $this->statusFilter))
            ->orderByRaw("CASE WHEN asset_status = 'damaged' THEN 0 ELSE 1 END")
            ->orderBy('qr_code_id')
            ->paginate(20);

        $damagedCount = MiddoBox::query()->where('asset_status', 'damaged')->count();

        return view('livewire.operation.middo-boxes', [
            'boxes' => $boxes,
            'damagedCount' => $damagedCount,
        ])->layout('layouts.private.app', ['title' => 'Middo Boxes']);
    }
}
