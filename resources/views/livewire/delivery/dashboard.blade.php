<div wire:key="delivery-dashboard" class="max-w-7xl mx-auto py-10 px-6 space-y-8">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Dashboard Overview</h1>
        <p class="text-sm font-semibold text-gray-500">Kitchen pickups and your Middo box runs.</p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <section class="bg-white border border-gray-200 rounded-2xl px-5 py-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
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
                                'px-3 py-1.5 rounded-xl text-xs font-bold border transition',
                                'bg-[#1E4630] text-white border-[#1E4630]' => $shiftStatus === $value,
                                'bg-white text-gray-600 border-gray-200 hover:border-middo-orange' => $shiftStatus !== $value,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach($tiles as $tile)
            <a href="{{ route($tile['route']) }}"
               class="block bg-white border border-gray-200 rounded-2xl px-6 py-8 text-center shadow-sm hover:shadow-md hover:border-middo-orange transition">
                <span class="text-lg font-black text-middo-dark">
                    {{ $tile['label'] }} ({{ $tile['count'] }})
                </span>
            </a>
        @endforeach
    </div>
</div>
