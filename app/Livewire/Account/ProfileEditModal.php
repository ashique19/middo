<?php

namespace App\Livewire\Account;

use App\Livewire\Concerns\ManagesProfilePayoutMethods;
use App\Models\Area;
use App\Models\City;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ProfileEditModal extends Component
{
    use ManagesProfilePayoutMethods;

    public bool $showModal = false;

    public bool $showPayoutMethods = false;

    public string $first_name = '';

    public string $last_name = '';

    public string $mobile = '';

    public ?string $email = null;

    public ?string $company_name = null;

    public ?string $address = null;

    public ?string $city_id = null;

    public ?string $area_id = null;

    public Collection $cities;

    public Collection $areas;

    public function mount(): void
    {
        $this->cities = City::orderBy('name')->get();
        $this->areas = collect();
    }

    #[On('open-profile-edit-modal')]
    public function openModal(): void
    {
        $this->resetErrorBag();
        $this->loadUser();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function updatedCityId($value): void
    {
        $this->refreshAreas($value);
        $this->area_id = null;
    }

    public function save(): void
    {
        $user = Auth::user();

        $rules = [
            'first_name' => 'required|string|min:2|max:255',
            'last_name' => 'required|string|min:2|max:255',
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile,'.$user->id],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'city_id' => 'required|exists:cities,id',
            'area_id' => 'required|exists:areas,id',
        ];

        if ($this->showPayoutMethods) {
            $rules = array_merge($rules, $this->payoutMethodValidationRules());
        }

        $validated = $this->validate($rules, array_merge([
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g., 01710123456).',
            'city_id.required' => 'Please select a city.',
            'area_id.required' => 'Please select an area.',
        ], $this->showPayoutMethods ? $this->payoutMethodValidationMessages() : []));

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->mobile = $validated['mobile'];
        $user->email = $validated['email'] ?: null;
        $user->company_name = $validated['company_name'] ?: null;
        $user->address = $validated['address'];
        $user->city_id = $validated['city_id'];
        $user->area_id = $validated['area_id'];

        if ($this->showPayoutMethods) {
            $this->savePayoutMethodsToUser($user);
        }

        $user->save();

        $this->dispatch('profile-updated');
        $this->closeModal();
    }

    protected function loadUser(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $user->loadMissing('role');
        $this->showPayoutMethods = $user->usesProfilePayoutMethods();

        $this->first_name = $user->first_name ?? '';
        $this->last_name = $user->last_name ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->email = $user->email;
        $this->company_name = $user->company_name;
        $this->address = $user->address;
        $this->city_id = $user->city_id ? (string) $user->city_id : null;
        $this->area_id = $user->area_id ? (string) $user->area_id : null;

        if ($this->showPayoutMethods) {
            $this->loadPayoutMethodsFromUser($user);
        }

        if ($this->city_id) {
            $this->refreshAreas($this->city_id);
        } else {
            $this->areas = collect();
        }
    }

    protected function refreshAreas($cityId): void
    {
        $this->areas = $cityId
            ? Area::where('city_id', $cityId)->orderBy('name')->get()
            : collect();
    }

    public function render()
    {
        return view('livewire.account.profile-edit-modal');
    }
}
