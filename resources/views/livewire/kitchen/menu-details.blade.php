<div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 space-y-6 sm:space-y-8">
    <div class="space-y-1">
        <a href="{{ route('kitchen.orders.active') }}" class="text-sm font-semibold text-middo-orange hover:underline">← My active orders</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark break-words">{{ $menuItem->name }}</h1>
        @if($menuItem->summary)
            <p class="text-sm font-semibold text-gray-500 max-w-2xl">{{ $menuItem->summary }}</p>
        @endif
    </div>

    <div class="space-y-3">
        <h2 class="text-lg font-black text-middo-dark">Meal items</h2>
        <p class="text-sm text-gray-500">Open a meal item to view its active recipe.</p>

        <div class="space-y-3">
            @forelse($mealItems as $meal)
                <div
                    wire:key="kitchen-menu-meal-{{ $meal['id'] }}"
                    class="bg-white border border-gray-200 rounded-2xl px-4 sm:px-5 py-4 shadow-sm flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 sm:gap-4 sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-1">
                        <h3 class="text-base font-bold text-middo-dark break-words">{{ $meal['name'] }}</h3>
                        @if($meal['summary'])
                            <p class="text-sm text-gray-500 line-clamp-2">{{ $meal['summary'] }}</p>
                        @endif
                        @if($meal['has_recipe'])
                            <p class="text-xs font-semibold text-gray-400">Recipe: {{ $meal['recipe_title'] }}</p>
                        @else
                            <p class="text-xs font-semibold text-amber-600">No active recipe</p>
                        @endif
                    </div>

                    @if($meal['has_recipe'])
                        <a
                            href="{{ route('kitchen.menus.meal-items.recipe', [$menuItem, $meal['id']]) }}"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 sm:py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition shrink-0">
                            View recipe
                        </a>
                    @endif
                </div>
            @empty
                <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
                    <p class="text-sm font-semibold text-gray-400 italic">No meal items attached to this menu.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
