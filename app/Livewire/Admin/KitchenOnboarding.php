<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class KitchenOnboarding extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function activate(int $id): void
    {
        $kitchen = $this->findPendingKitchen($id);
        $kitchen->update(['status' => 'active']);
        session()->flash('message', "{$kitchen->name} activated.");
    }

    public function suspend(int $id): void
    {
        $kitchen = $this->findPendingKitchen($id);
        $kitchen->update(['status' => 'inactive']);
        session()->flash('message', "{$kitchen->name} suspended.");
    }

    protected function findPendingKitchen(int $id): User
    {
        $kitchen = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'pending')
            ->findOrFail($id);

        return $kitchen;
    }

    public function render()
    {
        $kitchens = User::query()
            ->with(['city', 'area', 'role'])
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'pending')
            ->when($this->search, function ($query) {
                $query->where(function ($sub) {
                    $sub->where('first_name', 'like', '%'.$this->search.'%')
                        ->orWhere('last_name', 'like', '%'.$this->search.'%')
                        ->orWhere('mobile', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('address', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(15);

        return view('livewire.admin.kitchen-onboarding', compact('kitchens'))
            ->layout('layouts.private.app', ['title' => 'Kitchen Onboarding']);
    }
}
