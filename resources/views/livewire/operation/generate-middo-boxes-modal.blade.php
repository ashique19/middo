<div>
    <button
        type="button"
        wire:click="openModal"
        class="inline-flex items-center gap-2 bg-middo-orange text-white px-4 py-2 rounded-xl text-sm font-bold hover:opacity-90 transition whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Generate
    </button>

    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl my-8">
                @if(count($generatedBoxes) === 0)
                    <h2 class="text-xl font-bold text-middo-dark mb-2">Generate Box IDs</h2>
                    <p class="text-sm text-gray-500 mb-6">
                        New boxes will be created with auto-generated QR codes, default model, and warehouse status.
                    </p>

                    <form wire:submit="generate" class="space-y-4">
                        <div>
                            <label for="box-quantity" class="block text-sm font-semibold text-gray-700 mb-2">
                                Number of box IDs
                            </label>
                            <input
                                id="box-quantity"
                                wire:model="quantity"
                                type="number"
                                min="1"
                                max="500"
                                class="w-full border border-gray-300 rounded-xl shadow-sm focus:ring-middo-orange focus:border-middo-orange p-3"
                            >
                            @error('quantity')
                                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            <p class="font-semibold text-middo-dark mb-1">Auto-filled fields</p>
                            <ul class="space-y-1 text-xs">
                                <li>QR code: <span class="font-mono">MB-000001</span> format (sequential)</li>
                                <li>Model: Standard Insulated</li>
                                <li>Status: At Middo Warehouse</li>
                                <li>Uses: 0</li>
                            </ul>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-xl font-medium">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="bg-middo-orange text-white px-6 py-2 rounded-xl font-bold hover:opacity-90 transition">
                                Generate Boxes
                            </button>
                        </div>
                    </form>
                @else
                    <h2 class="text-xl font-bold text-middo-dark mb-2">Generated {{ count($generatedBoxes) }} box {{ str('ID')->plural(count($generatedBoxes)) }}</h2>
                    <p class="text-sm text-gray-500 mb-4">Newest IDs (print labels or open the detail page).</p>
                    <div class="max-h-64 overflow-y-auto rounded-xl border border-emerald-100 bg-emerald-50/50 divide-y divide-emerald-100 mb-4">
                        @foreach($generatedBoxes as $row)
                            <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <span class="font-mono font-bold text-middo-dark">{{ $row['qr'] }}</span>
                                    <span class="text-xs text-gray-500 ml-2">#{{ $row['id'] }}</span>
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <a href="{{ route('operation.middo-boxes.show', $row['id']) }}"
                                       class="text-xs font-bold text-middo-orange hover:underline">Details</a>
                                    <a href="{{ route('operation.middo-boxes.print', $row['id']) }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="text-xs font-bold text-gray-600 hover:underline">Print</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-end">
                        <button type="button" wire:click="done"
                                class="bg-middo-orange text-white px-6 py-2 rounded-xl font-bold hover:opacity-90 transition">
                            Done
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
