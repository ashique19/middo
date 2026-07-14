<div>
    <button type="button" wire:click="open" class="bg-middo-orange text-white px-4 py-2 rounded-lg text-sm font-bold hover:opacity-90">
        + Add Meal Item
    </button>

    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl my-8">
                <h2 class="text-xl font-bold mb-4">Add Meal Item</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Name</label>
                        <input wire:model="name" type="text" class="w-full border-gray-300 rounded-lg p-3">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Summary</label>
                        <textarea wire:model="summary" rows="2" class="w-full border-gray-300 rounded-lg p-3"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Other Costs (৳)</label>
                        <input wire:model="other_costs" type="number" min="0" step="1" class="w-full border-gray-300 rounded-lg p-3">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Note</label>
                        <textarea wire:model="note" rows="2" class="w-full border-gray-300 rounded-lg p-3"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Photo</label>
                        <input type="file" accept="image/*" onchange="mealItemCreateCrop(event)" class="text-sm">
                        @if($thumbnail)
                            <img src="{{ $thumbnail }}" class="mt-2 w-20 h-20 rounded-lg object-cover">
                        @endif
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-gray-600">Cancel</button>
                        <button type="submit" class="bg-middo-orange text-white px-5 py-2 rounded-lg font-bold">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @script
    <script>
        window.mealItemCreateCrop = function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => @this.set('thumbnail', ev.target.result);
            reader.readAsDataURL(file);
        };
    </script>
    @endscript
</div>
