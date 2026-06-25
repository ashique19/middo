<?php

namespace App\Livewire\Operation;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\MenuItem;
use Livewire\Attributes\On; // FIXED: Added missing import attribute

class MenuTable extends Component
{
    use WithPagination;

    public $search = '';

    // REMOVED: $listeners property to prevent duplicate event listener rendering cycles with #[On]

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function deleteItem(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        
        if ($item->thumbnail && file_exists(public_path($item->thumbnail))) {
            @unlink(public_path($item->thumbnail));
        }

        $item->delete();
        
        // Added this so the UI updates and pagination scales down if a row drops out
        $this->dispatch('menu-updated'); 
    }

    public function toggleFlag($id, $column)
    {
        $item = MenuItem::findOrFail($id);
        $item->update([$column => !$item->$column]);
        $this->dispatch('menu-updated');
    }

    /**
     * Listens for the 'menu-updated' dispatch signal globally
     * and forces this component to re-fetch database records instantly.
     */
    #[On('menu-updated')]
    public function refreshTable(): void
    {
        // Leaving this empty is perfectly fine! 
        // Just calling a method tagged with #[On] tells Livewire to re-run render()
    }

    public function render()
    {
        $items = MenuItem::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        return view('operation.menu.index', compact('items'));
    }
}