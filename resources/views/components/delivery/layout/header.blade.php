@props(['title' => 'Delivery'])

<header class="sticky top-0 z-30 kitchen-app-header pt-[env(safe-area-inset-top,0px)]">
    <div class="px-4 py-3 flex items-center gap-3">
        <a href="{{ route('delivery.dashboard') }}" class="shrink-0 w-10 h-10 rounded-2xl bg-[#1E4630] text-white grid place-items-center shadow-sm" aria-label="Middo Rider home">
            <span class="text-sm font-black tracking-tight">M</span>
        </a>

        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#8A735C]">Middo Rider</p>
            <h1 class="text-lg font-black text-[#2B1A11] truncate leading-tight">{{ $title }}</h1>
        </div>

        <button type="button"
                @click="$dispatch('open-profile-modal')"
                class="shrink-0 w-11 h-11 rounded-2xl bg-[#AB3F00]/12 text-[#AB3F00] grid place-items-center font-black"
                aria-label="Account">
            {{ strtoupper(substr(auth()->user()->first_name ?? 'R', 0, 1)) }}
        </button>
    </div>
</header>
