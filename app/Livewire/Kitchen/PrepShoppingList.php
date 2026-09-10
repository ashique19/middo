<?php

namespace App\Livewire\Kitchen;

use App\Support\KitchenIngredientRollup;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PrepShoppingList extends Component
{
    public string $deliveryDate = '';

    public string $ingredientSearch = '';

    public function mount(): void
    {
        $this->deliveryDate = now('Asia/Dhaka')->toDateString();
    }

    public function updatedIngredientSearch(): void
    {
        // Livewire re-renders; filtering happens in render().
    }

    public function render()
    {
        $rollup = KitchenIngredientRollup::forKitchen((int) Auth::id(), $this->deliveryDate);

        $query = mb_strtolower(trim($this->ingredientSearch));
        if ($query !== '') {
            $rollup['ingredients'] = array_values(array_filter(
                $rollup['ingredients'],
                fn (array $row) => str_contains(mb_strtolower((string) ($row['name'] ?? '')), $query)
                    || str_contains(mb_strtolower((string) ($row['unit'] ?? '')), $query)
            ));
        }

        return view('livewire.kitchen.prep-shopping-list', [
            'rollup' => $rollup,
        ])->layout('kitchen.layout.app', ['title' => 'Prep shopping list']);
    }
}
