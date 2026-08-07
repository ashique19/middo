<div wire:key="kitchen-dashboard" class="max-w-7xl mx-auto py-5 md:py-10 px-4 sm:px-6 space-y-5 md:space-y-8">
    <div class="space-y-1 hidden md:block">
        <h1 class="text-3xl font-bold text-middo-dark">Dashboard Overview</h1>
        <p class="text-sm font-semibold text-gray-500">Quick links to your kitchen work.</p>
    </div>

    <div class="md:hidden space-y-1 px-1">
        <p class="text-sm font-semibold text-[#635347]">Today’s kitchen hub</p>
        <p class="text-xs font-medium text-[#8A735C]">Tap a card to jump into prep, groups, or money.</p>
    </div>

    @if($insufficientBoxStock)
        <div class="rounded-2xl border border-red-300 bg-red-50 px-4 py-3.5 text-sm font-bold text-red-900">
            {{ \App\Support\KitchenBoxStock::dashboardWarningMessage() }}
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-4">
        @foreach($tiles as $tile)
            <a href="{{ route($tile['route']) }}"
               class="block rounded-[1.35rem] border border-[#E5DCC8] bg-[#FDFBF7]/95 px-4 py-5 md:px-6 md:py-8 text-left md:text-center shadow-[0_8px_24px_rgba(43,26,17,0.06)] hover:border-middo-orange hover:shadow-md transition min-h-[6.5rem] md:min-h-0">
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
