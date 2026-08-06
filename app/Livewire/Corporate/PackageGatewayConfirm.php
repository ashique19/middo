<?php

namespace App\Livewire\Corporate;

use App\Support\PackageGatewayCheckout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Legacy/recovery landing for package gateway tokens.
 * New flow completes the package automatically after payment; this page
 * finishes any paid session that still needs completion.
 */
class PackageGatewayConfirm extends Component
{
    public string $token = '';

    public bool $paid = false;

    public int $amount = 0;

    public string $mobile = '';

    public string $customerName = '';

    public string $packageName = '';

    public string $errorMessage = '';

    public string $statusMessage = '';

    public ?string $paymentUrl = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->bootFromSession();
    }

    public function retryCompletion(): void
    {
        $this->errorMessage = '';
        $this->completeIfPossible(redirectOnSuccess: true);
    }

    protected function bootFromSession(): void
    {
        $resolved = PackageGatewayCheckout::resolve($this->token, (int) Auth::id());

        if ($resolved === null) {
            $this->errorMessage = 'Payment session expired or invalid. Start package checkout again.';
            $this->paid = false;

            return;
        }

        $this->paid = (bool) $resolved['paid'];
        $this->amount = (int) $resolved['amount'];
        $this->mobile = (string) ($resolved['draft']['mobile'] ?? '');
        $this->customerName = (string) ($resolved['draft']['customer_name'] ?? '');
        $this->packageName = (string) ($resolved['intent']?->package?->name
            ?? ($resolved['metadata']['meal_package_id'] ?? 'Package'));

        $gatewayPayload = app(\App\Contracts\PaymentGateway::class)->find($this->token);
        $this->paymentUrl = is_array($gatewayPayload)
            ? ($gatewayPayload['payment_url'] ?? $gatewayPayload['redirect_url'] ?? null)
            : null;

        if ($this->paid) {
            $this->completeIfPossible(redirectOnSuccess: true);
        }
    }

    protected function completeIfPossible(bool $redirectOnSuccess): void
    {
        $completed = PackageGatewayCheckout::completeIfPaid($this->token);
        if (! ($completed['ok'] ?? false)) {
            $this->errorMessage = $completed['message'] ?? 'Could not finish package creation yet.';
            $this->statusMessage = '';

            return;
        }

        $subscriptionId = (int) ($completed['subscription_id'] ?? 0);
        session()->flash('message', $completed['message'] ?? 'Package prepaid successfully.');

        if (! $redirectOnSuccess) {
            return;
        }

        if ($subscriptionId > 0) {
            $this->redirect(route('corporates.packages.show', ['subscriptionId' => $subscriptionId]));

            return;
        }

        $this->redirect(route('corporates.packages.index'));
    }

    public function render()
    {
        return view('livewire.corporate.package-gateway-confirm')
            ->layout('layouts.public.app');
    }
}
