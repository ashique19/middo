<?php

namespace App\Livewire\Shared;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MenuTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $canManage = false;

    public function mount(): void
    {
        $this->canManage = Auth::user()?->role?->name === 'admin';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function deleteItem(int $id): void
    {
        $this->authorizeManage();

        $item = MenuItem::findOrFail($id);

        if ($item->thumbnail && file_exists(public_path($item->thumbnail))) {
            @unlink(public_path($item->thumbnail));
        }

        $item->delete();
        $this->dispatch('menu-updated');
    }

    public function toggleFlag(int $id, string $column): void
    {
        $this->authorizeManage();

        if (! in_array($column, ['is_featured', 'is_homepage'], true)) {
            return;
        }

        $item = MenuItem::findOrFail($id);
        $item->update([$column => ! $item->$column]);
        $this->dispatch('menu-updated');
    }

    #[On('menu-updated')]
    public function refreshTable(): void
    {
    }

    protected function authorizeManage(): void
    {
        abort_unless($this->canManage, 403);
    }

    public function render()
    {
        $items = MenuItem::query()
            ->withCount('mealItems')
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.shared.menu.table', [
            'items' => $items,
        ]);
    }
}
