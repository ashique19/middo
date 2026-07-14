<?php

namespace App\Livewire\Shared;

use App\Models\MealItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MealItemTable extends Component
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
        abort_unless($this->canManage, 403);

        $item = MealItem::findOrFail($id);
        if ($item->thumbnail && file_exists(public_path($item->thumbnail))) {
            @unlink(public_path($item->thumbnail));
        }
        $item->delete();
        $this->dispatch('meal-items-updated');
    }

    #[On('meal-items-updated')]
    public function refreshTable(): void
    {
    }

    public function render()
    {
        $items = MealItem::query()
            ->with('activeRecipe')
            ->withCount(['menuItems', 'recipes'])
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.shared.meal-items.table', [
            'items' => $items,
        ]);
    }
}
