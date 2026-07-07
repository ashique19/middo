<?php

namespace App\Livewire\Corporate;

use App\Models\City;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Profile extends Component
{
    public string $first_name = '';
    public string $last_name = '';
    public string $mobile = '';
    public ?string $email = null;
    public ?string $address = null;
    public $city_id = null;
    public $area_id = null;

    public array $citiesList = [];
    public array $areasList = [];

    public function mount(): void
    {
        $user = Auth::user();

        $this->first_name = $user->first_name ?? '';
        $this->last_name = $user->last_name ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->email = $user->email;
        $this->address = $user->address;
        $this->city_id = $user->city_id;
        $this->area_id = $user->area_id;

        $this->citiesList = City::all()->toArray();

        if ($this->city_id) {
            $this->loadAreasForSelectedCity($this->city_id);
        }
    }

    public function updatedCityId($value): void
    {
        $this->loadAreasForSelectedCity($value);
    }

    protected function loadAreasForSelectedCity($cityId): void
    {
        $city = City::find($cityId);
        $this->areasList = $city ? $city->areas->toArray() : [];

        if (! empty($this->areasList)) {
            $validIds = array_column($this->areasList, 'id');
            if (! in_array($this->area_id, $validIds)) {
                $this->area_id = $this->areasList[0]['id'];
            }
        } else {
            $this->area_id = null;
        }
    }

    public function save(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'first_name' => 'required|string|min:2|max:255',
            'last_name' => 'required|string|min:2|max:255',
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile,'.$user->id],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'address' => 'nullable|string|min:5|max:255',
            'city_id' => 'nullable|exists:cities,id',
            'area_id' => 'nullable|exists:areas,id',
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g., 01710123456).',
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->mobile = $validated['mobile'];
        $user->email = $validated['email'] ?: null;
        $user->address = $validated['address'];
        $user->city_id = $validated['city_id'];
        $user->area_id = $validated['area_id'];
        $user->save();

        session()->flash('status', 'Profile updated successfully.');
    }

    public function render()
    {
        return view('livewire.corporate.profile')
            ->layout('layouts.public.app');
    }
}
