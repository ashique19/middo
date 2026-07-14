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

    public ?string $lockedRole = null;

    public function mount(?string $lockedRole = null) {
        $this->roles = Role::all();
        $this->cities = \App\Models\City::all();
        $this->lockedRole = $lockedRole;

        if ($lockedRole) {
            $this->role_id = Role::where('name', $lockedRole)->value('id')
                ?? $this->roles->first()?->id;
        } else {
            $this->role_id = $this->roles->first()?->id;
        }
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
            'selectedCity' => 'nullable|exists:cities,id',
            'area_id'      => 'nullable|exists:areas,id',
        ]);

        if ($this->lockedRole) {
            $lockedId = Role::where('name', $this->lockedRole)->value('id');
            if ($lockedId) {
                $this->role_id = $lockedId;
            }
        }

        User::create([
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email ?: null,
            'mobile'     => $this->mobile,
            'password'   => Hash::make($this->password),
            'address'    => $this->address,
            'city_id'    => $this->selectedCity ?: null,
            'area_id'    => $this->area_id ?: null,
            'role_id'    => $this->role_id,
            'status'     => 'active',
            'is_mobile_verified' => true,
        ]);

        $locked = $this->lockedRole;
        $this->reset();
        $this->lockedRole = $locked;
        $this->roles = Role::all();
        $this->cities = \App\Models\City::all();
        $this->areas = [];
        $this->role_id = $locked
            ? (Role::where('name', $locked)->value('id') ?? $this->roles->first()?->id)
            : $this->roles->first()?->id;
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