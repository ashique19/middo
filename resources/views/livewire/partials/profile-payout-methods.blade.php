{{-- Saved payout destinations on kitchen/delivery profile --}}
@php
    $bankNames = \App\Support\BdBanks::bankNames();
    $bankCities = \App\Support\BdBanks::citiesFor($bankBankName);
    $bankBranches = \App\Support\BdBanks::branchesFor($bankBankName, $bankCity);
@endphp
<div class="space-y-4">
    <div>
        <h3 class="text-sm font-bold text-middo-dark {{ isset($headingClass) ? $headingClass : '' }}">{{ $heading ?? 'Withdrawal methods' }}</h3>
        <p class="text-xs text-gray-500 mt-1">{{ $hint ?? 'Save bank, bKash, and Nagad details once. When you request a withdrawal, just pick a channel.' }}</p>
    </div>

    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Preferred channel</label>
        <select wire:model="preferredPayoutChannel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
            @foreach(\App\Support\PayoutChannel::all() as $channel)
                <option value="{{ $channel }}">{{ \App\Support\PayoutChannel::label($channel) }}</option>
            @endforeach
        </select>
        @error('preferredPayoutChannel') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 space-y-3">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Bank</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Bank</label>
                <select wire:model.live="bankBankName" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                    <option value="">Select bank</option>
                    @foreach($bankNames as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('bankBankName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">City</label>
                <select wire:model.live="bankCity" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm" @disabled($bankBankName === '')>
                    <option value="">Select city</option>
                    @foreach($bankCities as $city)
                        <option value="{{ $city }}">{{ $city }}</option>
                    @endforeach
                </select>
                @error('bankCity') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Branch</label>
                <select wire:model="bankBranch" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm" @disabled($bankCity === '')>
                    <option value="">Select branch</option>
                    @foreach($bankBranches as $branch)
                        <option value="{{ $branch }}">{{ $branch }}</option>
                    @endforeach
                </select>
                @error('bankBranch') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Account name</label>
                <input type="text" wire:model="bankAccountName" placeholder="Letters, dots, hyphens"
                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                @error('bankAccountName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Account number</label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" wire:model="bankAccountNumber" placeholder="Digits only"
                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                @error('bankAccountNumber') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 space-y-3">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">bKash</p>
        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">Personal number</label>
            <input type="text" inputmode="numeric" wire:model="bkashMobile" placeholder="01XXXXXXXXX"
                   class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
            @error('bkashMobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 space-y-3">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Nagad</p>
        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">Personal number</label>
            <input type="text" inputmode="numeric" wire:model="nagadMobile" placeholder="01XXXXXXXXX"
                   class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
            @error('nagadMobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
