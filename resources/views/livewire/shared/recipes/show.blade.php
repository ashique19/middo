<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <nav class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-400">
            <a href="{{ $this->mealItemsIndexRoute() }}" class="text-middo-orange hover:underline">Meal items</a>
            <span>/</span>
            <a href="{{ $this->mealItemShowRoute() }}" class="text-middo-orange hover:underline">{{ $mealItem?->name ?? 'Meal' }}</a>
            <span>/</span>
            <span class="text-gray-600">{{ $recipe->title ?: 'Recipe' }}</span>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Recipe</p>
                <h1 class="text-3xl font-bold text-middo-dark">{{ $recipe->title ?: 'Untitled recipe' }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    For
                    <a href="{{ $this->mealItemShowRoute() }}" class="font-semibold text-middo-orange hover:underline">
                        {{ $mealItem?->name }}
                    </a>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($recipe->is_active)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">Active</span>
                @else
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase bg-gray-100 text-gray-600 border border-gray-200">Inactive</span>
                @endif
                @if($canManage)
                    <button type="button"
                        wire:click="$dispatch('open-recipe-manager', { mealItemId: {{ $recipe->meal_item_id }} })"
                        class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                        Manage recipes
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Ingredients</p>
            <p class="text-2xl font-black text-middo-dark">{{ $ingredients->count() }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Ingredient cost</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format($ingredientCost) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Photos</p>
            <p class="text-2xl font-black text-middo-dark">{{ count($photos) }}</p>
        </div>
    </div>

    @if($recipe->instructions)
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-2">
            <h2 class="text-lg font-bold text-middo-dark">Instructions</h2>
            <p class="text-sm text-gray-600 whitespace-pre-line leading-relaxed">{{ $recipe->instructions }}</p>
        </div>
    @endif

    @if($recipe->training_video_url)
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-2">
            <h2 class="text-lg font-bold text-middo-dark">Training video</h2>
            <a href="{{ $recipe->training_video_url }}" target="_blank" rel="noopener noreferrer"
               class="text-sm font-semibold text-middo-orange hover:underline break-all">
                {{ $recipe->training_video_url }}
            </a>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-middo-dark">Ingredients</h2>
            <p class="text-xs font-semibold text-gray-400 mt-0.5">Sorted by kitchen prep order</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[520px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Qty</th>
                        <th class="px-5 py-3">Unit</th>
                        <th class="px-5 py-3 text-right">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($ingredients as $row)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-gray-800">{{ $row->name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $row->quantity }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $row->unit ?: '—' }}</td>
                            <td class="px-5 py-3 text-right font-mono font-semibold text-middo-dark">
                                ৳{{ number_format((int) $row->cost) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-sm font-semibold text-gray-400 italic">
                                No ingredients listed for this recipe.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(count($photos) > 0)
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
            <h2 class="text-lg font-bold text-middo-dark">Photos</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($photos as $photo)
                    <img src="{{ $photo['url'] }}" alt="Recipe photo"
                         class="w-full h-40 object-cover rounded-xl border border-gray-200">
                @endforeach
            </div>
        </div>
    @endif

    @if($canManage)
        <livewire:shared.recipe-manager-modal />
    @endif
</div>
