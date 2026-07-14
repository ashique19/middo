<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl my-8">
                <div class="flex justify-between mb-4">
                    <h2 class="text-xl font-bold">{{ $readOnly ? 'View Meal Item' : 'Edit Meal Item' }}</h2>
                    <button type="button" wire:click="closeModal" class="text-gray-400">✕</button>
                </div>
                <form wire:submit="update" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Name</label>
                        <input wire:model="name" type="text" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Summary</label>
                        <textarea wire:model="summary" rows="2" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Recipe Ing. Cost</label>
                            <input value="৳{{ number_format($recipe_ingredient_cost) }}" readonly class="w-full bg-gray-100 border rounded-lg p-3">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Total Cost</label>
                            <input value="৳{{ number_format($total_cost) }}" readonly class="w-full bg-gray-100 border rounded-lg p-3">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Other Costs (৳)</label>
                        <input wire:model="other_costs" type="number" min="0" step="1" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Note</label>
                        <textarea wire:model="note" rows="2" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Photo</label>
                        @if($thumbnail)
                            <img src="{{ is_string($thumbnail) ? $thumbnail : '' }}" class="w-20 h-20 rounded-lg object-cover mb-2">
                        @endif
                        @unless($readOnly)
                            <input type="file" accept="image/*" onchange="mealItemEditCrop(event)" class="text-sm">
                        @endunless
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 text-gray-600">{{ $readOnly ? 'Close' : 'Cancel' }}</button>
                        @unless($readOnly)
                            <button type="submit" class="bg-middo-orange text-white px-5 py-2 rounded-lg font-bold">Update</button>
                        @endunless
                    </div>
                </form>
            </div>
        </div>
    @endif

    @script
    <script>
        window.mealItemEditCrop = function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => @this.set('thumbnail', ev.target.result);
            reader.readAsDataURL(file);
        };
    </script>
    @endscript
</div>
