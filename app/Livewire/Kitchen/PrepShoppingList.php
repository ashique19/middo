<?php

namespace App\Livewire\Kitchen;

use App\Support\KitchenIngredientRollup;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PrepShoppingList extends Component
{
    public string $deliveryDate = '';

    public function mount(): void
    {
        $this->deliveryDate = now('Asia/Dhaka')->toDateString();
    }

    public function render()
    {
        $rollup = KitchenIngredientRollup::forKitchen((int) Auth::id(), $this->deliveryDate);

        return view('livewire.kitchen.prep-shopping-list', [
            'rollup' => $rollup,
        ])->layout('kitchen.layout.app', ['title' => 'Prep shopping list']);
    }
}
