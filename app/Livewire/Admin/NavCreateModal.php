<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Nav;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

class NavCreateModal extends Component
{
    public $showModal = false;
    public $title, $route_name, $parent_id, $role_id;
    public $isChild = false;
    public $availableRoutes;

    public function mount($roleId) {
        $this->role_id = $roleId;
        $this->availableRoutes = collect(Route::getRoutes())->map(fn($r) => $r->getName())->filter()->sort()->values();
    }

    public function save() {
        $this->validate([
            'title' => 'required',
            'route_name' => 'required',
            'role_id' => 'required'
        ]);

        Nav::create([
            'title' => $this->title,
            'route_name' => $this->route_name,
            'parent_id' => $this->isChild ? $this->parent_id : null,
            'role_id' => $this->role_id,
            'order' => Nav::where('role_id', $this->role_id)->count() + 1
        ]);

        $this->dispatch('nav-updated');
    }

    public function render() {
        return view('livewire.admin.navs.nav-create-modal', [
            'parents' => Nav::where('role_id', $this->role_id)->whereNull('parent_id')->get()
        ]);
    }
}