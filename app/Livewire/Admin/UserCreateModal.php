<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserCreateModal extends Component
{
    public $showModal = false;
    public $first_name, $last_name, $email, $password, $mobile, $address, $role_id;
    
    // Use these for the dropdowns
    public $selectedCity; 
    public $area_id;
    
    public $roles, $cities, $areas = [];
    public $mobileExists = false;

    public function mount() {
        $this->roles = Role::all();
        $this->role_id = $this->roles->first()?->id;
        $this->cities = \App\Models\City::all();
    }

    // This runs automatically whenever $selectedCity changes
    public function updatedSelectedCity($cityId) {
        // Populate areas based on the selected city
        $this->areas = \App\Models\Area::where('city_id', $cityId)->get();
        $this->area_id = null; // Reset area when city changes
    }

    public function save() {
        $this->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'email'        => 'nullable|email|unique:users,email',
            'mobile'       => ['required', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile'],
            'password'     => 'required|min:6',
            'address'      => 'nullable|string',
            'role_id'      => 'required|exists:roles,id',
            'selectedCity' => 'required|exists:cities,id', // Matches the dropdown
            'area_id'      => 'required|exists:areas,id',  // Matches the dropdown
        ]);

        User::create([
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email ?: null,
            'mobile'     => $this->mobile,
            'password'   => Hash::make($this->password),
            'address'    => $this->address,
            'city_id'    => $this->selectedCity, // Use the ID from the dropdown
            'area_id'    => $this->area_id,      // Use the ID from the dropdown
            'role_id'    => $this->role_id,
            'status'     => 'active'
        ]);

        $this->reset(); 
        $this->showModal = false;
        $this->dispatch('user-updated');
    }


    

    // ... add this function to check mobile
    public function updatedMobile($value)
    {
        // Regex for BD number
        if (preg_match('/^01[3-9]\d{8}$/', $value)) {
            $this->mobileExists = \App\Models\User::where('mobile', $value)->exists();
        } else {
            $this->mobileExists = false;
        }
    }

    public function render() {
        return view('livewire.admin.users.user-create-modal');
    }
}