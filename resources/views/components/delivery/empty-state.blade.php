{{-- Shared empty state for delivery PWA lists --}}
@props([
    'title' => 'Nothing here',
    'message' => 'Check back soon.',
    'icon' => 'inbox',
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-[#E5DCC8] bg-[#FDFBF7] px-5 py-10 text-center shadow-sm']) }}>
    <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-[#1E4630]/10 text-[#1E4630]">
        @if($icon === 'runs')
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v9m11-1a2 2 0 104 0m-5 0a2 2 0 104 0m-5 0h-2"/></svg>
        @elseif($icon === 'boxes')
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        @elseif($icon === 'cash')
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        @else
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4m16 0H4"/></svg>
        @endif
    </div>
    <p class="text-base font-black text-[#2B1A11]">{{ $title }}</p>
    <p class="mt-1 text-sm font-semibold text-[#8A735C]">{{ $message }}</p>
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
