<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\PayoutChannel;

trait ManagesProfilePayoutMethods
{
    public string $preferredPayoutChannel = PayoutChannel::CASH;

    public string $bankBankName = '';

    public string $bankAccountName = '';

    public string $bankAccountNumber = '';

    public string $bkashAccountName = '';

    public string $bkashMobile = '';

    public string $nagadAccountName = '';

    public string $nagadMobile = '';

    protected function loadPayoutMethodsFromUser(User $user): void
    {
        $methods = $user->normalizedPayoutMethods();
        $this->preferredPayoutChannel = $user->preferredPayoutChannel();

        $bank = is_array($methods[PayoutChannel::BANK] ?? null) ? $methods[PayoutChannel::BANK] : [];
        $this->bankBankName = (string) ($bank['bank_name'] ?? '');
        $this->bankAccountName = (string) ($bank['account_name'] ?? '');
        $this->bankAccountNumber = (string) ($bank['account_number'] ?? '');

        $bkash = is_array($methods[PayoutChannel::BKASH] ?? null) ? $methods[PayoutChannel::BKASH] : [];
        $this->bkashAccountName = (string) ($bkash['account_name'] ?? '');
        $this->bkashMobile = (string) ($bkash['mobile'] ?? '');

        $nagad = is_array($methods[PayoutChannel::NAGAD] ?? null) ? $methods[PayoutChannel::NAGAD] : [];
        $this->nagadAccountName = (string) ($nagad['account_name'] ?? '');
        $this->nagadMobile = (string) ($nagad['mobile'] ?? '');
    }

    /**
     * @return array<string, string>
     */
    protected function payoutMethodValidationRules(): array
    {
        return [
            'preferredPayoutChannel' => 'required|in:'.implode(',', PayoutChannel::all()),
            'bankBankName' => 'nullable|string|max:120',
            'bankAccountName' => 'nullable|string|max:120',
            'bankAccountNumber' => 'nullable|string|max:64',
            'bkashAccountName' => 'nullable|string|max:120',
            'bkashMobile' => 'nullable|string|max:32',
            'nagadAccountName' => 'nullable|string|max:120',
            'nagadMobile' => 'nullable|string|max:32',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayoutMethodsPayload(): array
    {
        return [
            'preferred' => $this->preferredPayoutChannel,
            PayoutChannel::BANK => [
                'bank_name' => $this->bankBankName,
                'account_name' => $this->bankAccountName,
                'account_number' => $this->bankAccountNumber,
            ],
            PayoutChannel::BKASH => [
                'account_name' => $this->bkashAccountName,
                'mobile' => $this->bkashMobile,
            ],
            PayoutChannel::NAGAD => [
                'account_name' => $this->nagadAccountName,
                'mobile' => $this->nagadMobile,
            ],
        ];
    }

    protected function savePayoutMethodsToUser(User $user): void
    {
        $user->storePayoutMethods($this->buildPayoutMethodsPayload());
    }
}
