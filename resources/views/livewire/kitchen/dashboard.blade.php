<div wire:key="kitchen-dashboard" class="max-w-7xl mx-auto py-10 px-6 space-y-8">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Dashboard Overview</h1>
        <p class="text-sm font-semibold text-gray-500">Quick links to your kitchen work.</p>
    </div>

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
