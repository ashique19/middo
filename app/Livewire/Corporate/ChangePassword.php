<?php

namespace App\Livewire\Corporate;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ChangePassword extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

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

        session()->flash('status', 'Password changed successfully.');
    }

    public function render()
    {
        return view('livewire.corporate.change-password')
            ->layout('layouts.public.app');
    }
}
