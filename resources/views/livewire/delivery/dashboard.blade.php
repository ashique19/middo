<div wire:key="delivery-dashboard" class="max-w-7xl mx-auto py-5 md:py-10 px-4 sm:px-6 space-y-5 md:space-y-8">
    <div class="space-y-1 hidden md:block">
        <h1 class="text-3xl font-bold text-middo-dark">Dashboard Overview</h1>
        <p class="text-sm font-semibold text-gray-500">Kitchen pickups and your Middo box runs.</p>
    </div>

    <div class="md:hidden space-y-1 px-1">
        <p class="text-sm font-semibold text-[#635347]">Rider hub</p>
        <p class="text-xs font-medium text-[#8A735C]">Pickups, boxes, cash, and wallet.</p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <section class="bg-white border border-gray-200 rounded-2xl px-4 sm:px-5 py-4 shadow-sm space-y-3">
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Shift</p>
                <p class="text-sm font-bold text-middo-dark">{{ $shiftLabel }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Off shift / unable blocks accepting new lunch &amp; custom runs. Ask ops to reassign parcels if you cannot continue.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($shiftOptions as $value => $label)
                    <button type="button"
                            wire:click="setShift('{{ $value }}')"
                            @class([
                                'px-3 py-2 sm:py-1.5 rounded-xl text-xs font-bold border transition',
                                'bg-[#1E4630] text-white border-[#1E4630]' => $shiftStatus === $value,
                                'bg-white text-gray-600 border-gray-200 hover:border-middo-orange' => $shiftStatus !== $value,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-4">
        @foreach($tiles as $tile)
            <a href="{{ route($tile['route']) }}"
               class="block rounded-[1.35rem] border border-[#E5DCC8] bg-[#FDFBF7]/95 px-4 py-5 md:px-6 md:py-8 text-left md:text-center shadow-[0_8px_24px_rgba(43,26,17,0.06)] hover:border-middo-orange hover:shadow-md transition min-h-[6.5rem] md:min-h-0 md:bg-white md:border-gray-200">
                <span class="block text-[11px] md:text-sm font-bold uppercase tracking-wide text-[#8A735C] md:hidden">
                    {{ $tile['label'] }}
                </span>
                <span class="mt-1 md:mt-0 block text-2xl md:text-lg font-black text-[#2B1A11] md:text-middo-dark">
                    <span class="md:hidden">{{ $tile['count'] }}</span>
                    <span class="hidden md:inline">{{ $tile['label'] }} ({{ $tile['count'] }})</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
