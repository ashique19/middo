<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl my-8">
                <div class="flex items-start justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800">{{ $readOnly ? 'View Menu Item' : 'Edit Menu Item' }}</h2>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form wire:submit="update" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                        <input wire:model="name" type="text" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price (৳)</label>
                            <input wire:model.live.debounce.250ms="price" type="number" min="0" step="1" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Commission (%)</label>
                            <input wire:model.live.debounce.250ms="kitchen_commission_percentage" type="number" min="0" step="0.01" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kitchen Commission</label>
                        <input value="৳{{ number_format($kitchen_commission) }}" type="text" readonly class="w-full bg-gray-100 border border-gray-200 rounded-lg p-3">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery share (৳ / item)</label>
                        <input wire:model="delivery_commission" type="number" min="0" step="1" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meals Cost (auto)</label>
                            <input value="৳{{ number_format($meals_cost) }}" type="text" readonly class="w-full bg-gray-100 border border-gray-200 rounded-lg p-3">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Other Cost (৳)</label>
                            <input wire:model="other_cost" type="number" min="0" step="1" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Summary</label>
                        <textarea wire:model="summary" rows="2" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                        <textarea wire:model="note" rows="2" @disabled($readOnly) class="w-full border-gray-300 rounded-lg p-3 disabled:bg-gray-50"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thumbnail</label>
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center">
                                @if($thumbnail)
                                    <img src="{{ is_string($thumbnail) ? $thumbnail : '' }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-[10px] text-gray-400">No image</span>
                                @endif
                            </div>
                            @unless($readOnly)
                                <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium">
                                    Select File
                                    <input type="file" class="hidden" accept="image/*" onchange="initSharedMenuEditCropper(event)">
                                </label>
                            @endunless
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2"><input wire:model="is_featured" type="checkbox" @disabled($readOnly) class="rounded"> Featured</label>
                            <label class="flex items-center gap-2"><input wire:model="is_homepage" type="checkbox" @disabled($readOnly) class="rounded"> Homepage</label>
                        </div>
                        <input wire:model="display_order" type="number" @disabled($readOnly) class="w-20 border-gray-300 rounded-lg p-2 text-sm disabled:bg-gray-50">
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">{{ $readOnly ? 'Close' : 'Cancel' }}</button>
                        @unless($readOnly)
                            <button type="submit" class="bg-middo-orange text-white px-6 py-2 rounded-lg font-medium">Update Item</button>
                        @endunless
                    </div>
                </form>
            </div>
        </div>

        <div id="sharedMenuEditCropModal" class="hidden fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4">
            <div class="bg-white p-4 rounded-xl w-full max-w-lg">
                <div class="max-h-[70vh] overflow-hidden">
                    <img id="sharedMenuEditCropImage" class="max-w-full">
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" onclick="closeSharedMenuEditCrop()" class="px-4 py-2 text-gray-600">Cancel</button>
                    <button type="button" onclick="confirmSharedMenuEditCrop()" class="bg-middo-orange text-white px-6 py-2 rounded-lg">Crop & Save</button>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        let sharedMenuEditCropper;
        window.initSharedMenuEditCropper = function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => {
                document.getElementById('sharedMenuEditCropImage').src = ev.target.result;
                document.getElementById('sharedMenuEditCropModal').classList.remove('hidden');
                if (sharedMenuEditCropper) sharedMenuEditCropper.destroy();
                sharedMenuEditCropper = new Cropper(document.getElementById('sharedMenuEditCropImage'), { aspectRatio: 1, viewMode: 1 });
            };
            reader.readAsDataURL(file);
        };
        window.closeSharedMenuEditCrop = function() {
            document.getElementById('sharedMenuEditCropModal').classList.add('hidden');
            if (sharedMenuEditCropper) sharedMenuEditCropper.destroy();
        };
        window.confirmSharedMenuEditCrop = function() {
            @this.set('thumbnail', sharedMenuEditCropper.getCroppedCanvas({ width: 400, height: 400 }).toDataURL('image/jpeg'));
            closeSharedMenuEditCrop();
        };
    </script>
    @endscript
</div>
