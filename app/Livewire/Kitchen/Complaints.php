<?php

namespace App\Livewire\Kitchen;

use App\Support\KitchenComplaints;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Complaints extends Component
{
    use WithPagination;

    public string $category = '';

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $kitchenId = (int) Auth::id();

        $query = KitchenComplaints::scopedRootsQuery($kitchenId)
            ->with(['order.menuItem', 'order.user', 'createdBy'])
            ->latest('id');

        if ($this->category !== '') {
            $query->where('category', $this->category);
        }

        return view('livewire.kitchen.complaints', [
            'complaints' => $query->paginate(20),
        ])->layout('kitchen.layout.app', ['title' => 'Complaints']);
    }
}
