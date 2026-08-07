{{-- Saved payout destinations on kitchen/delivery profile --}}
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
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Bank name</label>
                <input type="text" wire:model="bankBankName" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Account name</label>
                <input type="text" wire:model="bankAccountName" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Account number</label>
                <input type="text" wire:model="bankAccountNumber" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                @error('bankAccountNumber') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 space-y-3">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">bKash</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Account name</label>
                <input type="text" wire:model="bkashAccountName" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Mobile</label>
                <input type="text" wire:model="bkashMobile" placeholder="01XXXXXXXXX" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                @error('bkashMobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 space-y-3">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Nagad</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Account name</label>
                <input type="text" wire:model="nagadAccountName" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1">Mobile</label>
                <input type="text" wire:model="nagadMobile" placeholder="01XXXXXXXXX" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                @error('nagadMobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>
