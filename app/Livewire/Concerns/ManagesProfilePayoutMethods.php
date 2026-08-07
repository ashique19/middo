<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\BdBanks;
use App\Support\PayoutChannel;
use Illuminate\Validation\Rule;

trait ManagesProfilePayoutMethods
{
    public string $preferredPayoutChannel = PayoutChannel::BANK;

    public string $bankBankName = '';

    public string $bankCity = '';

    public string $bankBranch = '';

    public string $bankAccountName = '';

    public string $bankAccountNumber = '';

    public string $bkashMobile = '';

    public string $nagadMobile = '';

    public function updatedBankBankName(): void
    {
        $this->bankCity = '';
        $this->bankBranch = '';
    }

    public function updatedBankCity(): void
    {
        $this->bankBranch = '';
    }

    protected function loadPayoutMethodsFromUser(User $user): void
    {
        $methods = $user->normalizedPayoutMethods();
        $this->preferredPayoutChannel = $user->preferredPayoutChannel();

        $bank = is_array($methods[PayoutChannel::BANK] ?? null) ? $methods[PayoutChannel::BANK] : [];
        $this->bankBankName = (string) ($bank['bank_name'] ?? '');
        $this->bankCity = (string) ($bank['city'] ?? '');
        $this->bankBranch = (string) ($bank['branch'] ?? '');
        $this->bankAccountName = (string) ($bank['account_name'] ?? '');
        $this->bankAccountNumber = (string) ($bank['account_number'] ?? '');

        $bkash = is_array($methods[PayoutChannel::BKASH] ?? null) ? $methods[PayoutChannel::BKASH] : [];
        $this->bkashMobile = (string) ($bkash['mobile'] ?? '');

        $nagad = is_array($methods[PayoutChannel::NAGAD] ?? null) ? $methods[PayoutChannel::NAGAD] : [];
        $this->nagadMobile = (string) ($nagad['mobile'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payoutMethodValidationRules(): array
    {
        $bankTouched = $this->bankSectionTouched();
        $bkashTouched = trim($this->bkashMobile) !== '';
        $nagadTouched = trim($this->nagadMobile) !== '';

        $rules = [
            'preferredPayoutChannel' => 'required|in:'.implode(',', PayoutChannel::partnerChannels()),
            'bankBankName' => ['nullable', 'string', 'max:120'],
            'bankCity' => ['nullable', 'string', 'max:120'],
            'bankBranch' => ['nullable', 'string', 'max:120'],
            'bankAccountName' => ['nullable', 'string', 'max:120'],
            'bankAccountNumber' => ['nullable', 'string', 'max:32'],
            'bkashMobile' => ['nullable', 'string', 'max:11'],
            'nagadMobile' => ['nullable', 'string', 'max:11'],
        ];

        if ($bankTouched) {
            $rules['bankBankName'] = ['required', 'string', 'max:120', Rule::in(BdBanks::bankNames())];
            $rules['bankCity'] = ['required', 'string', 'max:120', Rule::in(BdBanks::citiesFor($this->bankBankName))];
            $rules['bankBranch'] = ['required', 'string', 'max:120', Rule::in(BdBanks::branchesFor($this->bankBankName, $this->bankCity))];
            $rules['bankAccountName'] = ['required', 'string', 'min:2', 'max:120', 'regex:'.PayoutChannel::ACCOUNT_NAME_PATTERN];
            $rules['bankAccountNumber'] = ['required', 'string', 'min:5', 'max:32', 'regex:'.PayoutChannel::ACCOUNT_NUMBER_PATTERN];
        }

        if ($bkashTouched) {
            $rules['bkashMobile'] = ['required', 'regex:'.PayoutChannel::PERSONAL_MOBILE_PATTERN];
        }

        if ($nagadTouched) {
            $rules['nagadMobile'] = ['required', 'regex:'.PayoutChannel::PERSONAL_MOBILE_PATTERN];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function payoutMethodValidationMessages(): array
    {
        return [
            'bankAccountName.regex' => 'Account name may only contain letters, spaces, dots, and hyphens.',
            'bankAccountNumber.regex' => 'Account number must be digits only.',
            'bkashMobile.regex' => 'Provide a valid 11-digit bKash personal number (e.g., 01710123456).',
            'nagadMobile.regex' => 'Provide a valid 11-digit Nagad personal number (e.g., 01710123456).',
            'bankBankName.in' => 'Select a bank from the list.',
            'bankCity.in' => 'Select a city from the list.',
            'bankBranch.in' => 'Select a branch from the list.',
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
                'city' => $this->bankCity,
                'branch' => $this->bankBranch,
                'account_name' => $this->bankAccountName,
                'account_number' => $this->bankAccountNumber,
            ],
            PayoutChannel::BKASH => [
                'mobile' => $this->bkashMobile,
            ],
            PayoutChannel::NAGAD => [
                'mobile' => $this->nagadMobile,
            ],
        ];
    }

    protected function savePayoutMethodsToUser(User $user): void
    {
        $user->storePayoutMethods($this->buildPayoutMethodsPayload());
    }

    protected function bankSectionTouched(): bool
    {
        return trim($this->bankBankName) !== ''
            || trim($this->bankCity) !== ''
            || trim($this->bankBranch) !== ''
            || trim($this->bankAccountName) !== ''
            || trim($this->bankAccountNumber) !== '';
    }
}
