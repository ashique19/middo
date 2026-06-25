<div>
        <div>
        {{-- Trigger Button --}}
        <button wire:click="$set('showModal', true)" class="bg-middo-orange text-white px-4 py-2 rounded-lg hover:opacity-90 transition">
            + Add New Item
        </button>

        @if($showModal)
            <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
                <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl animate-in fade-in zoom-in duration-200">
                    <h2 class="text-xl font-bold mb-6 text-gray-800">Add Menu Item</h2>
                    
                    <form wire:submit="save" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Item Name</label>
                            <input wire:model="name" type="text" placeholder="Item Name" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3">
                            @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Price (৳)</label>
                            <input 
                                id="menu_price"
                                wire:model.live.debounce.250ms="price" 
                                type="number" 
                                min="0" 
                                step="0.01" 
                                placeholder="Price (৳)" 
                                oninput="this.value = this.value ? parseFloat(this.value) : ''; updateKitchenCommission()"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3"
                            >
                            @error('price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kitchen Commission (%)</label>
                            <input 
                                id="menu_commission_percentage"
                                wire:model.live.debounce.250ms="kitchen_commission_percentage" 
                                type="number" 
                                min="0" 
                                step="0.01" 
                                placeholder="Commission % of Price" 
                                oninput="this.value = this.value ? parseFloat(this.value) : ''; updateKitchenCommission()"
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3"
                            >
                            @error('kitchen_commission_percentage') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Calculated Kitchen Commission</label>
                            {{-- Changed to wire:model or simple binding so it tracks state explicitly --}}
                            <input id="menu_commission_value" value="{{ number_format($kitchen_commission, 2) }}" type="text" readonly class="w-full bg-gray-100 border border-gray-200 rounded-lg shadow-sm p-3 text-gray-700">
                            <p class="text-xs text-gray-500 mt-1">Auto-calculated from price × commission percentage.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Item Summary</label>
                            <textarea wire:model="summary" placeholder="Item Summary" rows="2" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-3"></textarea>
                            @error('summary') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

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
                                    Select File
                                    <input type="file" class="hidden" accept="image/*" onchange="initCropper(event)">
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
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">Save Item</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- Cropper Logic (Placed outside main modal to avoid re-rendering issues) --}}
    <div id="cropModal" class="hidden fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4">
        <div class="bg-white p-4 rounded-xl w-full max-w-lg">
            <div class="max-h-[70vh] overflow-hidden">
                <img id="imageToCrop" class="max-w-full">
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeCrop()" class="px-4 py-2 text-gray-600">Cancel</button>
                <button type="button" onclick="confirmCrop()" class="bg-blue-600 text-white px-6 py-2 rounded-lg">Crop & Save</button>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <script>
        let cropper;
        function initCropper(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('imageToCrop').src = e.target.result;
                document.getElementById('cropModal').classList.remove('hidden');
                cropper = new Cropper(document.getElementById('imageToCrop'), { aspectRatio: 1, viewMode: 1 });
            };
            reader.readAsDataURL(file);
        }
        function closeCrop() {
            document.getElementById('cropModal').classList.add('hidden');
            cropper.destroy();
        }
        function confirmCrop() {
            const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
            const dataUrl = canvas.toDataURL('image/jpeg');
            
            // This calls the Livewire component property directly
            @this.set('thumbnail', dataUrl); 
            
            closeCrop();
        }

        function updateKitchenCommission() {
            const priceInput = document.getElementById('menu_price');
            const percentInput = document.getElementById('menu_commission_percentage');
            const commissionInput = document.getElementById('menu_commission_value');

            const price = parseFloat(priceInput?.value) || 0;
            const percent = parseFloat(percentInput?.value) || 0;
            const commission = ((price * percent) / 100).toFixed(2);

            if (commissionInput) commissionInput.value = commission;

            // Update Livewire property `kitchen_commission`
            @this.set('kitchen_commission', parseFloat(commission));
        }
    </script>

</div>