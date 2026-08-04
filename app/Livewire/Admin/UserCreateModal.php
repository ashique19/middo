<?php

namespace App\Livewire\Admin;

use App\Models\Area;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\RiderCommission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class UserCreateModal extends Component
{
    public $showModal = false;

    public $first_name, $last_name, $email, $password, $mobile, $address, $role_id;

    public $selectedCity;

    public $area_id;

    /** @var array<int, string> */
    public array $selectedAreaIds = [];

    /** @var array<string, string|null> */
    public array $commissionOverrides = [];

    public $roles, $cities, $areas = [];

    public $mobileExists = false;

    public ?string $lockedRole = null;

    public function mount(?string $lockedRole = null)
    {
        $this->roles = Role::all();
        $this->cities = City::all();
        $this->lockedRole = $lockedRole;
        $this->resetCommissionOverrides();

        if ($lockedRole) {
            $this->role_id = Role::where('name', $lockedRole)->value('id')
                ?? $this->roles->first()?->id;
        } else {
            $this->role_id = $this->roles->first()?->id;
        }
    }

    public function updatedSelectedCity($cityId)
    {
        $this->areas = Area::where('city_id', $cityId)->get();
        $this->area_id = null;
        $this->selectedAreaIds = [];
    }

    public function getIsDeliveryFormProperty(): bool
    {
        if ($this->lockedRole === 'delivery') {
            return true;
        }

        $roleName = Role::query()->whereKey($this->role_id)->value('name');

        return $roleName === 'delivery';
    }

    public function save()
    {
        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users,email',
            'mobile' => ['required', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile'],
            'password' => 'required|min:6',
            'address' => 'nullable|string',
            'role_id' => 'required|exists:roles,id',
            'selectedCity' => 'nullable|exists:cities,id',
        ];

        if ($this->isDeliveryForm) {
            $rules['selectedAreaIds'] = 'nullable|array';
            $rules['selectedAreaIds.*'] = 'integer|exists:areas,id';
            foreach (DeliveryRunType::all() as $type) {
                $rules['commissionOverrides.'.$type] = 'nullable|integer|min:0|max:100000';
            }
        } else {
            $rules['area_id'] = 'nullable|exists:areas,id';
        }

        $this->validate($rules);

        if ($this->lockedRole) {
            $lockedId = Role::where('name', $this->lockedRole)->value('id');
            if ($lockedId) {
                $this->role_id = $lockedId;
            }
        }

        $primaryAreaId = $this->isDeliveryForm
            ? ($this->selectedAreaIds[0] ?? null)
            : ($this->area_id ?: null);

        $user = User::create([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email ?: null,
            'mobile' => $this->mobile,
            'password' => Hash::make($this->password),
            'address' => $this->address,
            'city_id' => $this->selectedCity ?: null,
            'area_id' => $primaryAreaId,
            'role_id' => $this->role_id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'rider_commission_overrides' => $this->isDeliveryForm
                ? RiderCommission::normalizeOverridesInput($this->commissionOverrides)
                : null,
        ]);

        if ($this->isDeliveryForm && Schema::hasTable('area_user')) {
            $user->areas()->sync(array_map('intval', $this->selectedAreaIds));
        }

        $locked = $this->lockedRole;
        $this->reset();
        $this->lockedRole = $locked;
        $this->roles = Role::all();
        $this->cities = City::all();
        $this->areas = [];
        $this->selectedAreaIds = [];
        $this->resetCommissionOverrides();
        $this->role_id = $locked
            ? (Role::where('name', $locked)->value('id') ?? $this->roles->first()?->id)
            : $this->roles->first()?->id;
        $this->showModal = false;
        $this->dispatch('user-updated');
    }

    public function updatedMobile($value)
    {
        if (preg_match('/^01[3-9]\d{8}$/', $value)) {
            $this->mobileExists = User::where('mobile', $value)->exists();
        } else {
            $this->mobileExists = false;
        }
    }

    protected function resetCommissionOverrides(): void
    {
        $this->commissionOverrides = [];
        foreach (DeliveryRunType::all() as $type) {
            $this->commissionOverrides[$type] = null;
        }
    }

    public function render()
    {
        return view('livewire.admin.users.user-create-modal', [
            'runTypes' => DeliveryRunType::all(),
        ]);
    }
}
