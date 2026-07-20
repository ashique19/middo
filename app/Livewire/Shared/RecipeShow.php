<?php

namespace App\Livewire\Shared;

use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class RecipeShow extends Component
{
    public Recipe $recipe;

    public function mount(Recipe $recipe): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);

        $recipe->load([
            'mealItem',
            'ingredients' => fn ($q) => $q->orderBy('sort_order'),
            'photos' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        $this->recipe = $recipe;
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function mealItemShowRoute(): string
    {
        return route($this->rolePrefix().'.meal-items.show', $this->recipe->meal_item_id);
    }

    public function mealItemsIndexRoute(): string
    {
        return route($this->rolePrefix().'.meal-items.index');
    }

    public function render()
    {
        $photos = $this->recipe->photos
            ->map(fn ($photo) => [
                'url' => Storage::disk('public')->url($photo->path),
            ])
            ->all();

        $ingredientCost = (int) $this->recipe->ingredients->sum('cost');

        return view('livewire.shared.recipes.show', [
            'recipe' => $this->recipe,
            'mealItem' => $this->recipe->mealItem,
            'ingredients' => $this->recipe->ingredients,
            'photos' => $photos,
            'ingredientCost' => $ingredientCost,
            'canManage' => Auth::user()?->role?->name === 'admin',
        ])->layout('layouts.private.app', [
            'title' => ($this->recipe->title ?: 'Recipe').' · Recipe',
        ]);
    }
}
