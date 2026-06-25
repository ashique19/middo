<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Role;
use App\Models\City;
use App\Models\Area;

class UserEditModal extends Component
{
    public User $user;
    public bool $showModal = false;

    // Form fields
    public $first_name, $last_name, $email, $mobile, $address, $city_id, $area_id, $role_id;
    
    // Collections
    public $roles, $cities, $areas = [];

    public function mount(User $user)
    {
        $this->user = $user;
        
        // Populate fields
        $this->first_name = $user->first_name;
        $this->last_name  = $user->last_name;
        $this->email      = $user->email;
        $this->mobile     = $user->mobile;
        $this->address    = $user->address;
        $this->city_id    = (string) $user->city_id; // Cast to string for select matching
        $this->area_id    = (string) $user->area_id;
        $this->role_id    = (string) $user->role_id;
        
        $this->roles  = Role::all();
        $this->cities = City::all();
        
        // Initial area population
        if ($this->city_id) {
            $this->refreshAreas($this->city_id);
        }
    }

    public function updatedCityId($value)
    {
        $this->refreshAreas($value);
        $this->area_id = null; // Clear selection when city changes
    }

    private function refreshAreas($cityId)
    {
        $this->areas = Area::where('city_id', $cityId)->get();
    }

    public function save()
    {
        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'mobile'     => 'required|regex:/^01[3-9]\d{8}$/|unique:users,mobile,' . $this->user->id,
            'role_id'    => 'required|exists:roles,id',
            'city_id'    => 'nullable|exists:cities,id',
            'area_id'    => 'nullable|exists:areas,id',
        ]);

        $this->user->update([
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            'mobile'     => $this->mobile,
            'address'    => $this->address,
            'city_id'    => $this->city_id ?: null,
            'area_id'    => $this->area_id ?: null,
            'role_id'    => $this->role_id,
        ]);

        $this->showModal = false;
        $this->dispatch('user-updated');
    }

    public function render()
    {
        return view('livewire.admin.users.user-edit-modal');
    }
}