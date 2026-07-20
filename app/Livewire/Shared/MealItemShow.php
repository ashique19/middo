<?php

namespace App\Livewire\Shared;

use App\Models\MealItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MealItemShow extends Component
{
    public MealItem $mealItem;

    public function mount(MealItem $mealItem): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);

        $mealItem->load([
            'recipes' => fn ($q) => $q->withCount('ingredients')->orderByDesc('is_active')->orderBy('title'),
            'menuItems',
            'activeRecipe',
        ]);

        $this->mealItem = $mealItem;
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function indexRoute(): string
    {
        return route($this->rolePrefix().'.meal-items.index');
    }

    public function recipeShowRoute(int $recipeId): string
    {
        return route($this->rolePrefix().'.recipes.show', $recipeId);
    }

    public function menuShowRoute(int $menuItemId): string
    {
        return route($this->rolePrefix().'.menu.show', $menuItemId);
    }

    public function render()
    {
        $item = $this->mealItem;

        return view('livewire.shared.meal-items.show', [
            'item' => $item,
            'canManage' => Auth::user()?->role?->name === 'admin',
        ])->layout('layouts.private.app', [
            'title' => $item->name.' · Meal item',
        ]);
    }
}
