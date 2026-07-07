<?php

namespace App\Livewire\Account;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ProfileModal extends Component
{
    public bool $showModal = false;

    public string $successMessage = '';

    public string $first_name = '';
    public string $last_name = '';
    public string $mobile = '';
    public ?string $email = null;
    public ?string $address = null;

    public function mount(): void
    {
        $this->loadUser();
    }

    protected function loadUser(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->first_name = $user->first_name ?? '';
        $this->last_name = $user->last_name ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->email = $user->email;
        $this->address = $user->address;
    }

    #[On('open-profile-modal')]
    public function openModal(): void
    {
        $this->successMessage = '';
        $this->resetErrorBag();
        $this->loadUser();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'first_name' => 'required|string|min:2|max:255',
            'last_name' => 'required|string|min:2|max:255',
            'mobile' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile,'.$user->id],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'address' => 'nullable|string|max:1000',
        ], [
            'mobile.regex' => 'Provide a valid 11-digit mobile number (e.g., 01710123456).',
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->mobile = $validated['mobile'];
        $user->email = $validated['email'] ?: null;
        $user->address = $validated['address'];
        $user->save();

        $this->successMessage = 'Profile updated successfully.';
        $this->dispatch('profile-updated');
    }

    public function render()
    {
        return view('livewire.account.profile-modal');
    }
}
