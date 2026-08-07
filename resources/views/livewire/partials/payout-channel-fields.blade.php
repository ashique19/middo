{{-- Shared kitchen/rider withdrawal: pick a saved payout channel --}}
@php
    $payoutUser = auth()->user();
    $channelConfigured = $payoutUser?->hasCompletePayoutMethod($payoutChannel) ?? false;
    $channelDetails = $payoutUser?->payoutDetailsFor($payoutChannel) ?? [];
    $needsProfile = \App\Support\PayoutChannel::requiresProfileDetails($payoutChannel);
    $isKitchenUser = $payoutUser?->isKitchen() ?? false;
@endphp
<div>
    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Payout channel</label>
    <select wire:model.live="payoutChannel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled($disabled ?? false)>
        @foreach(\App\Support\PayoutChannel::partnerChannels() as $channel)
            <option value="{{ $channel }}">{{ \App\Support\PayoutChannel::label($channel) }}</option>
        @endforeach
    </select>
    @error('payoutChannel') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

@if($needsProfile && $channelConfigured)
    <div class="rounded-xl border border-emerald-100 bg-emerald-50/80 px-3 py-2.5 space-y-1">
        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Paying to your saved {{ \App\Support\PayoutChannel::label($payoutChannel) }}</p>
        <p class="text-sm font-semibold text-emerald-900">{{ \App\Support\PayoutChannel::detailsSummary($payoutChannel, $channelDetails) }}</p>
        @if($isKitchenUser)
            <a href="{{ route('kitchen.profile') }}" class="inline-block text-xs font-bold text-middo-orange hover:underline">Change in profile</a>
        @else
            <button type="button" wire:click="$dispatch('open-profile-edit-modal')" class="text-xs font-bold text-middo-orange hover:underline">Change in profile</button>
        @endif
    </div>
@elseif($needsProfile)
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 space-y-1">
        <p class="text-sm font-semibold text-amber-900">
            Add your {{ \App\Support\PayoutChannel::label($payoutChannel) }} details in profile first, then request this payout.
        </p>
        @if($isKitchenUser)
            <a href="{{ route('kitchen.profile') }}" class="inline-block text-xs font-bold text-middo-orange hover:underline">Open profile</a>
        @else
            <button type="button" wire:click="$dispatch('open-profile-edit-modal')" class="text-xs font-bold text-middo-orange hover:underline">Edit profile</button>
        @endif
    </div>
@endif
