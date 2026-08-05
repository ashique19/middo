{{-- Shared kitchen/rider withdrawal payout destination fields --}}
<div>
    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Payout channel</label>
    <select wire:model.live="payoutChannel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled($disabled ?? false)>
        @foreach(\App\Support\PayoutChannel::all() as $channel)
            <option value="{{ $channel }}">{{ \App\Support\PayoutChannel::label($channel) }}</option>
        @endforeach
    </select>
    @error('payoutChannel') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

@if($payoutChannel === \App\Support\PayoutChannel::BANK)
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Bank name</label>
            <input type="text" wire:model="payoutBankName" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled($disabled ?? false)>
        </div>
        <div>
            <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Account name</label>
            <input type="text" wire:model="payoutAccountName" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled($disabled ?? false)>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Account number</label>
            <input type="text" wire:model="payoutAccountNumber" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled($disabled ?? false)>
            @error('payoutAccountNumber') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
@elseif(in_array($payoutChannel, [\App\Support\PayoutChannel::BKASH, \App\Support\PayoutChannel::NAGAD], true))
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Account name</label>
            <input type="text" wire:model="payoutAccountName" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled($disabled ?? false)>
        </div>
        <div>
            <label class="block text-xs font-bold uppercase text-gray-400 mb-1">{{ \App\Support\PayoutChannel::label($payoutChannel) }} mobile</label>
            <input type="text" wire:model="payoutMobile" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled($disabled ?? false)>
            @error('payoutMobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
@else
    <p class="text-xs text-gray-500">Cash payout — Middo pays from till on approval.</p>
@endif
