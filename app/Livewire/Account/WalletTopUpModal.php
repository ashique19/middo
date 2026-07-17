<?php

namespace App\Livewire\Account;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class WalletTopUpModal extends Component
{
    public bool $showModal = false;

    public string $successMessage = '';

    public string $amount = '500';

    #[On('open-wallet-top-up-modal')]
    public function openModal(): void
    {
        $this->successMessage = '';
        $this->amount = '500';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function topUp(): void
    {
        $this->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:500000'],
        ], [
            'amount.min' => 'Minimum top-up is ৳100.',
            'amount.max' => 'Maximum top-up is ৳500,000.',
        ]);

        $user = Auth::user();
        $amount = (int) round((float) $this->amount);

        $user->balance = (int) $user->balance + $amount;
        $user->save();

        $this->successMessage = 'Balance topped up by ৳'.number_format($amount).'.';
        $this->dispatch('wallet-balance-updated');
    }

    public function render()
    {
        return view('livewire.account.wallet-top-up-modal');
    }
}
