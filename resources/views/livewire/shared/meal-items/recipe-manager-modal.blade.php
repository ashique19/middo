<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-3xl shadow-2xl my-8 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $canManage ? ($editing ? ($recipeId ? 'Edit Recipe' : 'New Recipe') : 'Recipes') : 'Recipe' }}
                        </h2>
                        <p class="text-sm text-gray-500">Meal: <span class="font-semibold text-middo-dark">{{ $mealName }}</span></p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                @if(! $editing)
                    <div class="flex justify-between items-center mb-4">
                        <p class="text-sm text-gray-500">{{ count($recipes) }} recipe(s)</p>
                        @if($canManage)
                            <button type="button" wire:click="startCreate" class="bg-middo-orange text-white px-4 py-2 rounded-lg text-sm font-bold">+ New Recipe</button>
                        @endif
                    </div>

                    <div class="space-y-3">
                        @forelse($recipes as $recipe)
                            <div wire:key="recipe-card-{{ $recipe['id'] }}" class="border border-gray-200 rounded-xl p-4 {{ $recipe['is_active'] ? 'ring-2 ring-middo-orange/30 bg-orange-50/30' : '' }}">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-bold text-gray-800">{{ $recipe['title'] }}</h3>
                                        @if($recipe['is_active'])
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Active</span>
                                        @endif
                                    </div>
                                    <div class="flex gap-2">
                                        @if($canManage && ! $recipe['is_active'])
                                            <button type="button" wire:click="activateRecipe({{ $recipe['id'] }})" class="text-xs font-bold text-emerald-700 border border-emerald-300 px-2 py-1 rounded-lg">Activate</button>
                                        @endif
                                        <button type="button" wire:click="startEdit({{ $recipe['id'] }})" class="text-xs font-bold border border-gray-300 px-2 py-1 rounded-lg">{{ $canManage ? 'Edit' : 'View' }}</button>
                                        @if($canManage)
                                            <button type="button" wire:click="deleteRecipe({{ $recipe['id'] }})" wire:confirm="Delete this recipe?" class="text-xs font-bold text-red-600 border border-red-200 px-2 py-1 rounded-lg">Delete</button>
                                        @endif
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mb-2">{{ $recipe['ingredient_count'] }} ingredients · ৳{{ number_format($recipe['ingredient_cost']) }} · {{ $recipe['photo_count'] }} photos</p>
                                @if($recipe['training_video_url'])
                                    <a href="{{ $recipe['training_video_url'] }}" target="_blank" class="text-xs text-middo-orange font-semibold underline">Training video</a>
                                @endif
                            </div>
                        @empty
                            <p class="text-center text-sm text-gray-400 italic py-8">No recipes yet.</p>
                        @endforelse
                    </div>
                @elseif(! $canManage)
                    {{-- Operation: read-only recipe detail --}}
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-bold text-middo-dark">{{ $title }}</h3>
                            @if($is_active)
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Active</span>
                            @endif
                        </div>

                        @if($instructions)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-1">Instructions</h4>
                                <p class="text-sm text-gray-600 whitespace-pre-line leading-relaxed">{{ $instructions }}</p>
                            </div>
                        @endif

                        @if($training_video_url)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-1">Training Video</h4>
                                <a href="{{ $training_video_url }}" target="_blank" rel="noopener noreferrer"
                                    class="text-sm font-semibold text-middo-orange underline break-all">
                                    {{ $training_video_url }}
                                </a>
                            </div>
                        @endif

                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Ingredients</h4>
                            <div class="overflow-x-auto rounded-xl border border-gray-200">
                                <table class="w-full text-sm text-left">
                                    <thead>
                                        <tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 border-b">
                                            <th class="px-3 py-2">Name</th>
                                            <th class="px-3 py-2">Qty</th>
                                            <th class="px-3 py-2">Unit</th>
                                            <th class="px-3 py-2 text-right">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($ingredients as $row)
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-800">{{ $row['name'] }}</td>
                                                <td class="px-3 py-2 text-gray-600">{{ $row['quantity'] }}</td>
                                                <td class="px-3 py-2 text-gray-600">{{ $row['unit'] ?: '—' }}</td>
                                                <td class="px-3 py-2 text-right font-semibold text-gray-800">৳{{ number_format((int) $row['cost']) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-3 py-6 text-center text-gray-400 italic">No ingredients listed.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if(count($existingPhotos) > 0)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">Photos</h4>
                                <div class="flex flex-wrap gap-3">
                                    @foreach($existingPhotos as $index => $path)
                                        <a href="{{ asset($path) }}" target="_blank" rel="noopener noreferrer" wire:key="view-photo-{{ $index }}">
                                            <img src="{{ asset($path) }}" alt="" class="w-24 h-24 rounded-xl object-cover border border-gray-200 hover:opacity-90">
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-end pt-2 border-t">
                            <button type="button" wire:click="closeModal" class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50">
                                Close
                            </button>
                        </div>
                    </div>
                @else
                    <form wire:submit="saveRecipe" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Title</label>
                            <input wire:model="title" type="text" class="w-full border-gray-300 rounded-lg p-3">
                            @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Instructions</label>
                            <textarea wire:model="instructions" rows="4" class="w-full border-gray-300 rounded-lg p-3"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Training Video URL</label>
                            <input wire:model="training_video_url" type="url" class="w-full border-gray-300 rounded-lg p-3" placeholder="https://...">
                            @error('training_video_url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium">Ingredients</label>
                                <button type="button" wire:click="addIngredientRow" class="text-xs font-bold text-middo-orange">+ Add line</button>
                            </div>
                            <div class="space-y-2">
                                @foreach($ingredients as $index => $row)
                                    <div wire:key="ing-row-{{ $index }}" class="grid grid-cols-12 gap-2 items-start">
                                        <input wire:model="ingredients.{{ $index }}.name" type="text" placeholder="Name" class="col-span-4 border-gray-300 rounded-lg p-2 text-sm">
                                        <input wire:model="ingredients.{{ $index }}.quantity" type="number" step="0.001" min="0" placeholder="Qty" class="col-span-2 border-gray-300 rounded-lg p-2 text-sm">
                                        <input wire:model="ingredients.{{ $index }}.unit" type="text" placeholder="Unit" class="col-span-2 border-gray-300 rounded-lg p-2 text-sm">
                                        <input wire:model="ingredients.{{ $index }}.cost" type="number" step="1" min="0" placeholder="Cost ৳" class="col-span-3 border-gray-300 rounded-lg p-2 text-sm">
                                        <button type="button" wire:click="removeIngredientRow({{ $index }})" class="col-span-1 text-red-500 text-sm">✕</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Photos</label>
                            <div class="flex flex-wrap gap-2 mb-2">
                                @foreach($existingPhotos as $index => $path)
                                    <div wire:key="photo-{{ $index }}" class="relative">
                                        <img src="{{ asset($path) }}" class="w-16 h-16 rounded-lg object-cover border">
                                        <button type="button" wire:click="removeExistingPhoto({{ $index }})" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs">×</button>
                                    </div>
                                @endforeach
                                @foreach($newPhotos as $index => $dataUrl)
                                    <img wire:key="new-photo-{{ $index }}" src="{{ $dataUrl }}" class="w-16 h-16 rounded-lg object-cover border">
                                @endforeach
                            </div>
                            <input type="file" accept="image/*" onchange="recipeAddPhoto(event)" class="text-sm">
                        </div>

                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input type="checkbox" wire:model="is_active" class="rounded text-middo-orange"> Set as active recipe
                        </label>

                        <div class="flex justify-end gap-3 pt-2 border-t">
                            <button type="button" wire:click="cancelEdit" class="px-4 py-2 text-gray-600">Back</button>
                            <button type="submit" class="bg-middo-orange text-white px-5 py-2 rounded-lg font-bold">Save Recipe</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @script
    <script>
        window.recipeAddPhoto = function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => @this.call('addPhotoDataUrl', ev.target.result);
            reader.readAsDataURL(file);
            e.target.value = '';
        };
    </script>
    @endscript
</div>
