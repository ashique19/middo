<?php

namespace App\Livewire\Account;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;

class ChangePasswordModal extends Component
{
    public bool $showModal = false;

    public string $successMessage = '';

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    #[On('open-change-password-modal')]
    public function openModal(): void
    {
        $this->successMessage = '';
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.confirmed' => 'The new password confirmation does not match.',
            'password.min' => 'The new password must be at least 8 characters.',
        ]);

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Your current password is incorrect.');

            return;
        }

        // The User model casts 'password' to 'hashed', so assigning the plain value hashes it on save.
        $user->password = $this->password;
        $user->save();

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->successMessage = 'Password changed successfully.';
    }

    public function render()
    {
        return view('livewire.account.change-password-modal');
    }
}
