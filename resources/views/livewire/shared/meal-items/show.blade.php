<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <nav class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-400">
            <a href="{{ $this->indexRoute() }}" class="text-middo-orange hover:underline">Meal items</a>
            <span>/</span>
            <span class="text-gray-600">{{ $item->name }}</span>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4 min-w-0">
                @if($item->thumbnail)
                    <img src="{{ asset($item->thumbnail) }}" alt="{{ $item->name }}"
                         class="w-20 h-20 rounded-2xl object-cover border border-gray-100 shrink-0">
                @else
                    <div class="w-20 h-20 rounded-2xl bg-gray-50 border border-dashed border-gray-200 flex items-center justify-center text-[10px] text-gray-400 shrink-0">
                        No Img
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Meal item</p>
                    <h1 class="text-3xl font-bold text-middo-dark truncate">{{ $item->name }}</h1>
                    @if($item->summary)
                        <p class="text-sm text-gray-500 mt-1">{{ $item->summary }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Ingredient cost</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format((int) $item->recipe_ingredient_cost) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Other cost</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format((int) $item->other_costs) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Total cost</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format((int) $item->total_cost) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Recipes</p>
            <p class="text-2xl font-black text-middo-dark">{{ $item->recipes->count() }}</p>
        </div>
    </div>

    @if($item->note)
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-2">Note</h2>
            <p class="text-sm font-semibold text-gray-800">{{ $item->note }}</p>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Recipes</h2>
                <p class="text-xs font-semibold text-gray-400 mt-0.5">Open a recipe to view its ingredients</p>
            </div>
            @if($canManage)
                <button type="button"
                    wire:click="$dispatch('open-recipe-manager', { mealItemId: {{ $item->id }} })"
                    class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                    Manage recipes
                </button>
            @endif
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse($item->recipes as $recipe)
                <li class="px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ $this->recipeShowRoute($recipe->id) }}"
                           class="font-bold text-middo-dark hover:text-middo-orange transition">
                            {{ $recipe->title ?: 'Untitled recipe' }}
                        </a>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            @if($recipe->is_active)
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Active</span>
                            @endif
                            <span class="text-[11px] font-semibold text-gray-400">
                                {{ $recipe->ingredients_count }} ingredient{{ $recipe->ingredients_count === 1 ? '' : 's' }}
                            </span>
                        </div>
                    </div>
                    <a href="{{ $this->recipeShowRoute($recipe->id) }}"
                       class="shrink-0 text-xs font-bold text-middo-orange hover:underline">
                        Ingredients →
                    </a>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm font-semibold text-gray-400 italic">
                    No recipes yet.
                </li>
            @endforelse
        </ul>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-middo-dark">Used in menus</h2>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse($item->menuItems as $menu)
                <li class="px-5 py-3.5 flex items-center justify-between gap-3 text-sm">
                    <a href="{{ $this->menuShowRoute($menu->id) }}"
                       class="font-bold text-middo-dark hover:text-middo-orange transition truncate">
                        {{ $menu->name }}
                    </a>
                    <p class="font-mono font-semibold text-gray-700 shrink-0">৳{{ number_format((int) $menu->price) }}</p>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm font-semibold text-gray-400 italic">
                    Not attached to any menu yet.
                </li>
            @endforelse
        </ul>
    </div>

    @if($canManage)
        <livewire:shared.recipe-manager-modal />
    @endif
</div>
