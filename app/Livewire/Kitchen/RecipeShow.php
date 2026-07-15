<?php

namespace App\Livewire\Kitchen;

use App\Models\MealItem;
use App\Models\MenuItem;
use App\Models\OrderGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class RecipeShow extends Component
{
    public MenuItem $menuItem;

    public MealItem $mealItem;

    public ?array $recipe = null;

    public function mount(MenuItem $menuItem, MealItem $mealItem): void
    {
        $kitchenId = Auth::id();

        abort_unless(
            OrderGroup::query()
                ->where('kitchen_id', $kitchenId)
                ->where('menu_id', $menuItem->id)
                ->exists(),
            404
        );

        abort_unless(
            $menuItem->mealItems()->where('meal_items.id', $mealItem->id)->exists(),
            404
        );

        $this->menuItem = $menuItem;
        $this->mealItem = $mealItem->load([
            'activeRecipe.ingredients',
            'activeRecipe.photos',
        ]);

        $active = $this->mealItem->activeRecipe;

        if ($active) {
            $this->recipe = [
                'id' => $active->id,
                'title' => $active->title,
                'instructions' => $active->instructions,
                'training_video_url' => $active->training_video_url,
                'ingredients' => $active->ingredients
                    ->map(fn ($row) => [
                        'name' => $row->name,
                        'quantity' => $row->quantity,
                        'unit' => $row->unit,
                    ])
                    ->all(),
                'photos' => $active->photos
                    ->map(fn ($photo) => [
                        'url' => Storage::disk('public')->url($photo->path),
                    ])
                    ->all(),
            ];
        }
    }

    public function render()
    {
        return view('livewire.kitchen.recipe-show')
            ->layout('layouts.private.app', [
                'title' => ($this->recipe['title'] ?? 'Recipe').' — '.$this->mealItem->name,
            ]);
    }
}
