<?php

namespace App\Livewire\Admin;

use App\Models\Area;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\RiderCommission;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class UserEditModal extends Component
{
    public User $user;

    public bool $showModal = false;

    public $first_name, $last_name, $email, $mobile, $address, $city_id, $area_id, $role_id;

    /** @var array<int, string> */
    public array $selectedAreaIds = [];

    /** @var array<string, string|null> */
    public array $commissionOverrides = [];

    public $roles, $cities, $areas = [];

    public function mount(User $user)
    {
        $this->user = $user->loadMissing(['areas', 'role']);

        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
        $this->mobile = $user->mobile;
        $this->address = $user->address;
        $this->city_id = (string) $user->city_id;
        $this->area_id = (string) $user->area_id;
        $this->role_id = (string) $user->role_id;

        $this->roles = Role::all();
        $this->cities = City::all();

        $this->resetCommissionOverrides();
        foreach (RiderCommission::overridesMap($user) as $type => $amount) {
            $this->commissionOverrides[$type] = (string) $amount;
        }

        if ($this->city_id) {
            $this->refreshAreas($this->city_id);
        }

        $pivotIds = $user->areas->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->selectedAreaIds = $pivotIds !== []
            ? $pivotIds
            : ($user->area_id ? [(string) $user->area_id] : []);
    }

    public function updatedCityId($value)
    {
        $this->refreshAreas($value);
        $this->area_id = null;
        $this->selectedAreaIds = [];
    }

    public function getIsDeliveryFormProperty(): bool
    {
        $roleName = Role::query()->whereKey($this->role_id)->value('name');

        return $roleName === 'delivery';
    }

    private function refreshAreas($cityId)
    {
        $this->areas = Area::where('city_id', $cityId)->get();
    }

    public function save()
    {
        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mobile' => 'required|regex:/^01[3-9]\d{8}$/|unique:users,mobile,'.$this->user->id,
            'role_id' => 'required|exists:roles,id',
            'city_id' => 'nullable|exists:cities,id',
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

        $primaryAreaId = $this->isDeliveryForm
            ? ($this->selectedAreaIds[0] ?? null)
            : ($this->area_id ?: null);

        $this->user->update([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'city_id' => $this->city_id ?: null,
            'area_id' => $primaryAreaId,
            'role_id' => $this->role_id,
            'rider_commission_overrides' => $this->isDeliveryForm
                ? RiderCommission::normalizeOverridesInput($this->commissionOverrides)
                : $this->user->rider_commission_overrides,
        ]);

        if ($this->isDeliveryForm && Schema::hasTable('area_user')) {
            $this->user->areas()->sync(array_map('intval', $this->selectedAreaIds));
        }

        $this->showModal = false;
        $this->dispatch('user-updated');
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
        return view('livewire.admin.users.user-edit-modal', [
            'runTypes' => DeliveryRunType::all(),
        ]);
    }
}
