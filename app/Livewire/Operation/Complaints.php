<?php

namespace App\Livewire\Operation;

use App\Models\OrderComplaint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class Complaints extends Component
{
    use WithPagination;

    public string $category = '';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function render()
    {
        $query = OrderComplaint::query()
            ->with(['order.menuItem', 'order.user', 'order.orderGroup.kitchen', 'createdBy'])
            ->latest('id');

        if (Schema::hasColumn('order_complaints', 'parent_id')) {
            $query->whereNull('parent_id');
        }

        if ($this->category !== '') {
            $query->where('category', $this->category);
        }

        return view('livewire.operation.complaints', [
            'complaints' => $query->paginate(20),
            'rolePrefix' => $this->rolePrefix(),
        ])->layout('layouts.private.app', ['title' => 'Complaints']);
    }
}
