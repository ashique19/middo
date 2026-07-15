<div class="max-w-7xl mx-auto py-10 px-6 space-y-8">
    <div class="space-y-1">
        <a href="{{ route('kitchen.menus.show', $menuItem) }}" class="text-sm font-semibold text-middo-orange hover:underline">← {{ $menuItem->name }}</a>
        <p class="text-sm font-semibold text-gray-500">Meal item</p>
        <h1 class="text-3xl font-bold text-middo-dark">{{ $mealItem->name }}</h1>
        @if($mealItem->summary)
            <p class="text-sm text-gray-500 max-w-2xl">{{ $mealItem->summary }}</p>
        @endif
    </div>

    @if($recipe)
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-6">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl font-black text-middo-dark">{{ $recipe['title'] }}</h2>
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Active recipe</span>
            </div>

            @if($recipe['instructions'])
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Instructions</h3>
                    <p class="text-sm text-gray-600 whitespace-pre-line leading-relaxed">{{ $recipe['instructions'] }}</p>
                </div>
            @endif

            @if($recipe['training_video_url'])
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Training video</h3>
                    <a href="{{ $recipe['training_video_url'] }}" target="_blank" rel="noopener noreferrer"
                       class="text-sm font-semibold text-middo-orange underline break-all">
                        {{ $recipe['training_video_url'] }}
                    </a>
                </div>
            @endif

            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Ingredients</h3>
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 border-b">
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">Qty</th>
                                <th class="px-3 py-2">Unit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recipe['ingredients'] as $row)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $row['quantity'] }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $row['unit'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-6 text-center text-gray-400 italic">No ingredients listed.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if(count($recipe['photos']) > 0)
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Photos</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($recipe['photos'] as $photo)
                            <img src="{{ $photo['url'] }}" alt="Recipe photo"
                                 class="w-full h-40 object-cover rounded-xl border border-gray-200">
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-400 italic">No active recipe for this meal item.</p>
        </div>
    @endif
</div>
