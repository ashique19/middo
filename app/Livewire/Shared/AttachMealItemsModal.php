<?php

namespace App\Livewire\Shared;

use App\Models\MealItem;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class AttachMealItemsModal extends Component
{
    public bool $showModal = false;

    public bool $canManage = false;

    public ?int $menuItemId = null;

    public string $menuName = '';

    public string $search = '';

    /** @var int[] */
    public array $selectedMealItemIds = [];

    public array $mealItems = [];

    #[On('open-attach-meal-items-modal')]
    public function openModal($menuItemId = null): void
    {
        $id = is_array($menuItemId) ? ($menuItemId['menuItemId'] ?? null) : $menuItemId;
        if (! $id) {
            return;
        }

        $menu = MenuItem::with('mealItems')->findOrFail((int) $id);
        $this->canManage = Auth::user()?->role?->name === 'admin';
        $this->menuItemId = $menu->id;
        $this->menuName = $menu->name;
        $this->selectedMealItemIds = $menu->mealItems->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->search = '';
        $this->loadMealItems();
        $this->showModal = true;
    }

    public function updatedSearch(): void
    {
        $this->loadMealItems();
    }

    public function toggleMeal(int $mealItemId): void
    {
        if (! $this->canManage) {
            return;
        }

        if (in_array($mealItemId, $this->selectedMealItemIds, true)) {
            $this->selectedMealItemIds = array_values(array_filter(
                $this->selectedMealItemIds,
                fn (int $id) => $id !== $mealItemId
            ));
        } else {
            $this->selectedMealItemIds[] = $mealItemId;
        }
    }

    public function save(): void
    {
        abort_unless($this->canManage && $this->menuItemId, 403);

        $menu = MenuItem::findOrFail($this->menuItemId);
        $sync = [];
        foreach (array_values($this->selectedMealItemIds) as $index => $mealId) {
            $sync[$mealId] = ['sort_order' => $index + 1];
        }
        $menu->mealItems()->sync($sync);
        $menu->recalculateMealsCost();

        $this->showModal = false;
        $this->dispatch('menu-updated');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    protected function loadMealItems(): void
    {
        if (! $this->canManage) {
            $menu = MenuItem::with(['mealItems' => fn ($q) => $q->orderByPivot('sort_order')])
                ->find($this->menuItemId);

            $this->mealItems = ($menu?->mealItems ?? collect())
                ->map(fn (MealItem $meal) => [
                    'id' => $meal->id,
                    'name' => $meal->name,
                    'total_cost' => $meal->total_cost,
                    'summary' => $meal->summary,
                ])
                ->values()
                ->all();

            return;
        }

        $this->mealItems = MealItem::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (MealItem $meal) => [
                'id' => $meal->id,
                'name' => $meal->name,
                'total_cost' => $meal->total_cost,
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.shared.menu.attach-meal-items-modal');
    }
}
