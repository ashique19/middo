<div>
    <button type="button" wire:click="open" class="bg-middo-orange text-white px-4 py-2 rounded-lg hover:opacity-90 transition text-sm font-bold">
        + Add New Item
    </button>

    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl my-8">
                <h2 class="text-xl font-bold mb-6 text-gray-800">Add Menu Item</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                        <input wire:model="name" type="text" class="w-full border-gray-300 rounded-lg p-3">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price (৳)</label>
                            <input wire:model.live.debounce.250ms="price" type="number" min="0" step="1" class="w-full border-gray-300 rounded-lg p-3">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Commission (%)</label>
                            <input wire:model.live.debounce.250ms="kitchen_commission_percentage" type="number" min="0" step="0.01" class="w-full border-gray-300 rounded-lg p-3">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kitchen Commission</label>
                        <input value="৳{{ number_format($kitchen_commission) }}" type="text" readonly class="w-full bg-gray-100 border border-gray-200 rounded-lg p-3">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Other Cost (৳)</label>
                        <input wire:model="other_cost" type="number" min="0" step="1" class="w-full border-gray-300 rounded-lg p-3">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Summary</label>
                        <textarea wire:model="summary" rows="2" class="w-full border-gray-300 rounded-lg p-3"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                        <textarea wire:model="note" rows="2" class="w-full border-gray-300 rounded-lg p-3"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thumbnail</label>
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center">
                                @if($thumbnail)
                                    <img src="{{ $thumbnail }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-[10px] text-gray-400">No image</span>
                                @endif
                            </div>
                            <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium">
                                Select File
                                <input type="file" class="hidden" accept="image/*" onchange="initSharedMenuCropper(event, 'create')">
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2"><input wire:model="is_featured" type="checkbox" class="rounded"> Featured</label>
                            <label class="flex items-center gap-2"><input wire:model="is_homepage" type="checkbox" class="rounded"> Homepage</label>
                        </div>
                        <input wire:model="display_order" type="number" placeholder="Order" class="w-20 border-gray-300 rounded-lg p-2 text-sm">
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" class="bg-middo-orange text-white px-6 py-2 rounded-lg font-medium">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="sharedMenuCropModal" class="hidden fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4">
        <div class="bg-white p-4 rounded-xl w-full max-w-lg">
            <div class="max-h-[70vh] overflow-hidden">
                <img id="sharedMenuCropImage" class="max-w-full">
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeSharedMenuCrop()" class="px-4 py-2 text-gray-600">Cancel</button>
                <button type="button" onclick="confirmSharedMenuCrop()" class="bg-middo-orange text-white px-6 py-2 rounded-lg">Crop & Save</button>
            </div>
        </div>
    </div>

    @assets
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    @endassets

    @script
    <script>
        let sharedMenuCropper;
        let sharedMenuCropTarget = 'create';

        window.initSharedMenuCropper = function(e, target) {
            const file = e.target.files[0];
            if (!file) return;
            sharedMenuCropTarget = target || 'create';
            const reader = new FileReader();
            reader.onload = (ev) => {
                document.getElementById('sharedMenuCropImage').src = ev.target.result;
                document.getElementById('sharedMenuCropModal').classList.remove('hidden');
                if (sharedMenuCropper) sharedMenuCropper.destroy();
                sharedMenuCropper = new Cropper(document.getElementById('sharedMenuCropImage'), { aspectRatio: 1, viewMode: 1 });
            };
            reader.readAsDataURL(file);
        };

        window.closeSharedMenuCrop = function() {
            document.getElementById('sharedMenuCropModal').classList.add('hidden');
            if (sharedMenuCropper) sharedMenuCropper.destroy();
        };

        window.confirmSharedMenuCrop = function() {
            const dataUrl = sharedMenuCropper.getCroppedCanvas({ width: 400, height: 400 }).toDataURL('image/jpeg');
            @this.set('thumbnail', dataUrl);
            closeSharedMenuCrop();
        };
    </script>
    @endscript
</div>
