<?php

namespace App\Livewire\Account;

use App\Support\CorporateWalletTopUp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class WalletTopUpModal extends Component
{
    public bool $showModal = false;

    public string $successMessage = '';

    public string $amount = '500';

    public ?string $paymentUrl = null;

    public ?string $paymentToken = null;

    #[On('open-wallet-top-up-modal')]
    public function openModal(): void
    {
        $this->successMessage = '';
        $this->amount = '500';
        $this->paymentUrl = null;
        $this->paymentToken = null;
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
        $checkout = CorporateWalletTopUp::createCheckout($user, $amount);

        $this->paymentToken = $checkout['token'];
        $this->paymentUrl = $checkout['payment_url'];
        $this->successMessage = '';
    }

    public function refreshAfterPayment(): void
    {
        if (! filled($this->paymentToken)) {
            return;
        }

        $result = CorporateWalletTopUp::creditIfPaid($this->paymentToken);

        if (! ($result['ok'] ?? false)) {
            $this->addError('amount', $result['message'] ?? 'Payment not completed yet. Finish checkout, then try again.');

            return;
        }

        $this->successMessage = $result['message'] ?? 'Balance topped up.';
        $this->paymentUrl = null;
        $this->dispatch('wallet-balance-updated');
    }

    public function render()
    {
        return view('livewire.account.wallet-top-up-modal');
    }
}
