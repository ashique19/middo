<?php

namespace App\Livewire\Account;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ProfileModal extends Component
{
    public bool $showModal = false;

    public string $name = '';
    public string $mobile = '';
    public ?string $address = null;
    public string $cityName = '';
    public string $areaName = '';

    #[On('open-profile-modal')]
    public function openModal(): void
    {
        $this->loadUser();
        $this->showModal = true;
    }

    #[On('profile-updated')]
    public function refreshProfile(): void
    {
        if ($this->showModal) {
            $this->loadUser();
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function openEditModal(): void
    {
        $this->closeModal();
        $this->dispatch('open-profile-edit-modal');
    }

    protected function loadUser(): void
    {
        $user = Auth::user()?->load([
            'city' => fn ($query) => $query->select('id', 'name'),
            'area' => fn ($query) => $query->select('id', 'name'),
        ]);

        if (! $user) {
            return;
        }

        $this->name = $user->name;
        $this->mobile = $user->mobile ?? '';
        $this->address = $user->address;
        $this->cityName = $user->city_name ?: '—';
        $this->areaName = $user->area_name ?: '—';
    }

    public function render()
    {
        return view('livewire.account.profile-modal');
    }
}
