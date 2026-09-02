@props(['title' => 'Kitchen'])

@php
    $unread = \App\Support\StaffAlerts::unreadCount((int) auth()->id());
@endphp

<header class="sticky top-0 z-30 kitchen-app-header pt-[env(safe-area-inset-top,0px)]">
    <div class="px-4 py-3 flex items-center gap-3">
        <a href="{{ route('kitchen.cash-handovers') }}"
           class="shrink-0 min-w-10 h-10 px-2.5 rounded-2xl bg-[#1E4630] text-white grid place-items-center shadow-sm"
           aria-label="Cash management">
            <span class="text-[11px] font-black tracking-tight">Cash</span>
        </a>

        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#8A735C]">Middo Kitchen</p>
            <h1 class="text-lg font-black text-[#2B1A11] truncate leading-tight">{{ $title }}</h1>
        </div>

        <a href="{{ route('kitchen.alerts') }}"
           class="relative shrink-0 w-11 h-11 rounded-2xl border border-[#E5DCC8] bg-white/80 grid place-items-center text-[#2B1A11]"
           aria-label="Alerts">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            @if($unread > 0)
                <span class="absolute -top-1 -right-1 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-middo-orange text-white text-[10px] font-black grid place-items-center">
                    {{ $unread > 9 ? '9+' : $unread }}
                </span>
            @endif
        </a>

        <button type="button"
                @click="$dispatch('open-profile-modal')"
                class="shrink-0 w-11 h-11 rounded-2xl bg-[#AB3F00]/12 text-[#AB3F00] grid place-items-center font-black"
                aria-label="Account">
            {{ strtoupper(substr(auth()->user()->first_name ?? 'K', 0, 1)) }}
        </button>
    </div>
</header>
