<?php

namespace App\Livewire\Public;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class MenuDisplay extends Component
{
    public Collection|array $menu;

    public function mount(Collection|array $menu): void
    {
        $this->menu = $menu instanceof Collection ? $menu->toArray() : $menu;
    }

    public function handleOrder(int $dishId)
    {
        // Authentication check interceptor
        if (!Auth::check()) {
            return redirect()->guest(route('login'));
        }

        // Pass payload securely to the modal downstream
        $this->dispatch('openOrderModal', ['dishId' => $dishId]);
    }

    public function render()
    {
        return view('livewire.public.menu-display', [
            'menu' => $this->menu,
        ]);
    }
}