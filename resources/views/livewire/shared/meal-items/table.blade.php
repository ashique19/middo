<div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-middo-dark">Meal items</h1>
            <p class="text-sm text-gray-500 mt-1">
                Meal items → recipes → ingredients.
                {{ $canManage ? 'Manage shared meal components and kitchen recipes.' : 'Browse meal items and recipes (read-only).' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <div class="relative w-64">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search meal items..."
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl shadow-sm focus:border-middo-orange focus:ring-middo-orange">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.603 10.601z" />
                </svg>
            </div>
            @if($canManage)
                <livewire:shared.meal-item-create-modal />
            @endif
        </div>
    </div>

    <div class="bg-white shadow-sm border border-gray-100 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[780px]">
                <thead>
                    <tr class="bg-gray-50 border-b text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4 w-20">Image</th>
                        <th class="p-4">Meal item</th>
                        <th class="p-4">Active recipe</th>
                        <th class="p-4 text-center">Recipes</th>
                        <th class="p-4">Cost</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($items as $item)
                        <tr wire:key="meal-row-{{ $item->id }}" class="hover:bg-gray-50/70">
                            <td class="p-4">
                                @if($item->thumbnail)
                                    <img src="{{ asset($item->thumbnail) }}" class="w-12 h-12 rounded-xl object-cover border" alt="">
                                @else
                                    <div class="w-12 h-12 rounded-xl bg-gray-50 border border-dashed flex items-center justify-center text-[10px] text-gray-400">No Img</div>
                                @endif
                            </td>
                            <td class="p-4">
                                <a href="{{ $this->showRoute($item) }}" class="font-semibold text-middo-dark hover:text-middo-orange transition">
                                    {{ $item->name }}
                                </a>
                                <div class="text-[11px] font-semibold text-middo-orange mt-0.5">
                                    <a href="{{ $this->showRoute($item) }}" class="hover:underline">Recipes →</a>
                                </div>
                                @if($item->summary)
                                    <div class="text-xs text-gray-400 truncate max-w-xs mt-0.5">{{ $item->summary }}</div>
                                @endif
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    In {{ $item->menu_items_count }} menu{{ $item->menu_items_count === 1 ? '' : 's' }}
                                </div>
                            </td>
                            <td class="p-4">
                                @if($item->activeRecipe && $this->recipeShowRoute($item->activeRecipe->id))
                                    <a href="{{ $this->recipeShowRoute($item->activeRecipe->id) }}"
                                       class="font-medium text-gray-800 hover:text-middo-orange transition">
                                        {{ $item->activeRecipe->title }}
                                    </a>
                                    <div class="text-[11px] font-semibold text-middo-orange mt-0.5">
                                        <a href="{{ $this->recipeShowRoute($item->activeRecipe->id) }}" class="hover:underline">Ingredients →</a>
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                <a href="{{ $this->showRoute($item) }}"
                                   class="inline-flex min-w-[2.5rem] justify-center px-2.5 py-1 rounded-lg text-sm font-bold text-middo-orange hover:bg-orange-50 transition">
                                    {{ $item->recipes_count }}
                                </a>
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-gray-800">৳{{ number_format($item->total_cost) }}</div>
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    Ing ৳{{ number_format($item->recipe_ingredient_cost) }}
                                    · Other ৳{{ number_format($item->other_costs) }}
                                </div>
                            </td>
                            <td class="p-4 text-right">
                                <div class="inline-flex gap-2">
                                    @if($canManage)
                                        <button type="button" wire:click="$dispatch('open-recipe-manager', { mealItemId: {{ $item->id }} })"
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 hover:border-middo-orange hover:text-middo-orange">
                                            Manage
                                        </button>
                                    @endif
                                    <button type="button" wire:click="$dispatch('editMealItem', { id: {{ $item->id }} })"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 border border-gray-200 hover:bg-middo-orange hover:text-white">
                                        {{ $canManage ? 'Edit' : 'View' }}
                                    </button>
                                    @if($canManage)
                                        <button type="button" wire:click="deleteItem({{ $item->id }})" wire:confirm="Delete this meal item and its recipes?"
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 text-red-600 hover:bg-red-50">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-12 text-center text-gray-400">No meal items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $items->links() }}</div>

    <livewire:shared.meal-item-edit-modal />
    <livewire:shared.recipe-manager-modal />
</div>
