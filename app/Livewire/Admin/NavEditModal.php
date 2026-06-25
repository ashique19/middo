<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Nav;
use App\Models\Role;

use Illuminate\Support\Facades\Route;



class NavEditModal extends Component
{
    public $nav;
    public $showModal = false;
    public $parents;
    public $roles;
    public $availableRoutes;
    // You MUST define these here so Livewire can track them
    public $title, $route_name, $parent_id, $role_id; 
    public $isChild = false;

    public function mount(Nav $nav)
    {
        $this->nav = $nav;
        $this->title = $nav->title;
        $this->route_name = $nav->route_name;
        $this->parent_id = $nav->parent_id;
        $this->role_id = $nav->role_id; // Now it will find this
        $this->isChild = !is_null($nav->parent_id);
        
        $this->parents = Nav::whereNull('parent_id')
                            ->where('id', '!=', $nav->id)
                            ->get();
        $this->roles = Role::all();

        $this->availableRoutes = collect(Route::getRoutes())
                                    ->map(fn($route) => $route->getName())
                                    ->filter()
                                    ->sort()
                                    ->values();
    }

    public function save()
    {
        $this->validate([
            'title' => 'required',
            'route_name' => 'required|string',
            'role_id' => 'required',
        ]);

        $this->nav->update([
            'title' => $this->title,
            'route_name' => $this->route_name,
            'parent_id' => $this->isChild ? $this->parent_id : null,
            'role_id' => $this->role_id,
        ]);
        
        $this->showModal = false;
        
        // Dispatch event to trigger JS reload
        $this->dispatch('nav-updated'); 
    }

    // app/Livewire/Admin/NavEditModal.php

    public function delete()
    {
        $this->nav->delete();
        
        $this->showModal = false;
        $this->dispatch('nav-updated'); // This triggers the page reload we set up earlier
    }

    public function render()
    {
        return view('livewire.admin.navs.nav-edit-modal');
    }
}