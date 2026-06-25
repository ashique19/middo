<div >
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl animate-in fade-in zoom-in duration-200">
                <h2 class="text-xl font-bold mb-6 text-gray-800">Edit Menu Item</h2>
                
                <form wire:submit="update" class="space-y-4">
                    <input wire:model="name" type="text" placeholder="Item Name" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Price (৳)</label>
                            <input 
                                wire:model.live.debounce.250ms="price" 
                                type="number" 
                                min="0" 
                                step="0.01" 
                                oninput="this.value = this.value ? parseFloat(this.value) : ''"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3"
                            >
                            @error('price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Commission (%)</label>
                            <input 
                                wire:model.live.debounce.250ms="kitchen_commission_percentage" 
                                type="number" 
                                min="0" 
                                step="0.01" 
                                oninput="this.value = this.value ? parseFloat(this.value) : ''"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Calculated Kitchen Commission</label>
                        <input value="৳{{ number_format($kitchen_commission, 2) }}" type="text" readonly class="w-full bg-gray-100 border border-gray-200 rounded-lg p-3 text-gray-700">
                    </div>

                    <textarea wire:model="summary" placeholder="Item Summary" rows="2" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3"></textarea>

                    {{-- Image Upload Section --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Thumbnail</label>
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center overflow-hidden bg-gray-50 shrink-0">
                                @if ($thumbnail)
                                    <img src="{{ is_string($thumbnail) ? $thumbnail : $thumbnail->temporaryUrl() }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-gray-400 text-[10px] text-center p-1">No image</span>
                                @endif
                            </div>
                            <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">
                                Change File
                                <input type="file" class="hidden" accept="image/*" onchange="initEditCropper(event)">
                            </label>
                        </div>
                        @error('thumbnail') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Settings Row --}}
                    <div class="flex items-center justify-between gap-4 pt-2">
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                <input wire:model="is_featured" type="checkbox" class="rounded text-blue-600"> Featured
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                <input wire:model="is_homepage" type="checkbox" class="rounded text-blue-600"> Homepage
                            </label>
                        </div>
                        <input wire:model="display_order" type="number" placeholder="Order" class="w-20 border-gray-300 rounded-lg p-2 text-sm">
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t mt-6">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Independent Crop Modal Overlay --}}
    {{-- Independent Crop Modal Overlay --}}
    <div id="editCropModal" class="hidden fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4">
        <div class="bg-white p-4 rounded-xl w-full max-w-lg">
            <div class="max-h-[70vh] overflow-hidden">
                <img id="imageToCropEdit" class="max-w-full">
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeEditCrop()" class="px-4 py-2 text-gray-600">Cancel</button>
                <button type="button" onclick="confirmEditCrop()" class="bg-blue-600 text-white px-6 py-2 rounded-lg">Crop & Apply</button>
            </div>
        </div>
    </div>

    {{-- FIXED: Wrapped in wire:ignore so Livewire DOM diffing never wipes out active Cropper variables --}}
    <div wire:ignore>
        <script>
            let editCropper = null;

            function initEditCropper(e) {
                const file = e.target.files[0];
                if (!file) return;

                // Clean up any old instance safely before starting a new one
                if (editCropper) {
                    editCropper.destroy();
                    editCropper = null;
                }

                const reader = new FileReader();
                reader.onload = (event) => {
                    const image = document.getElementById('imageToCropEdit');
                    image.src = event.target.result;
                    
                    document.getElementById('editCropModal').classList.remove('hidden');
                    
                    editCropper = new Cropper(image, { 
                        aspectRatio: 1, 
                        viewMode: 1,
                        autoCropArea: 1
                    });
                };
                reader.readAsDataURL(file);
            }

            function closeEditCrop() {
                document.getElementById('editCropModal').classList.add('hidden');
                if (editCropper) {
                    editCropper.destroy();
                    editCropper = null;
                }
            }

            function confirmEditCrop() {
                if (!editCropper) return;

                const dataUrl = editCropper.getCroppedCanvas({ width: 400, height: 400 }).toDataURL('image/jpeg');
                
                // Secure Livewire 3 context resolution
                @this.set('thumbnail', dataUrl);
                
                closeEditCrop();
            }
        </script>
    </div>

</div>
