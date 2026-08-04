<?php

namespace App\Livewire\Kitchen;

use App\Models\MenuItem;
use App\Models\OrderGroup;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MenuDetails extends Component
{
    public MenuItem $menuItem;

    public array $mealItems = [];

    public function mount(MenuItem $menuItem): void
    {
        $kitchenId = Auth::id();

        abort_unless(
            OrderGroup::query()
                ->where('kitchen_id', $kitchenId)
                ->where('menu_id', $menuItem->id)
                ->exists(),
            404
        );

        $this->menuItem = $menuItem->load([
            'mealItems' => fn ($query) => $query->with('activeRecipe')->orderByPivot('sort_order'),
        ]);

        $this->mealItems = $this->menuItem->mealItems
            ->map(fn ($meal) => [
                'id' => $meal->id,
                'name' => $meal->name,
                'summary' => $meal->summary,
                'thumbnail' => $meal->thumbnail,
                'has_recipe' => $meal->activeRecipe !== null,
                'recipe_title' => $meal->activeRecipe?->title,
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.kitchen.menu-details')
            ->layout('kitchen.layout.app', ['title' => $this->menuItem->name.' — Menu']);
    }
}
