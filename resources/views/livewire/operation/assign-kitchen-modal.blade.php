<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Assign Kitchen</h2>
                        <p class="text-sm text-gray-500 mt-1">Group: <span class="font-semibold text-middo-dark">{{ $groupName }}</span></p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none">
                        ✕
                    </button>
                </div>

                <form wire:submit="save" class="space-y-5">
                    <div>
                        <label for="kitchen-select" class="block text-sm font-semibold text-gray-700 mb-2">
                            Kitchen
                        </label>
                        <select
                            id="kitchen-select"
                            wire:model="selectedKitchenId"
                            class="w-full border border-gray-300 rounded-xl shadow-sm focus:border-middo-orange focus:ring-middo-orange p-3 text-sm">
                            <option value="">Unassigned</option>
                            @foreach($kitchens as $kitchen)
                                <option value="{{ $kitchen['id'] }}">{{ $kitchen['name'] }}</option>
                            @endforeach
                        </select>
                        @error('selectedKitchenId')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <p class="text-xs text-gray-500">
                        Current assignment: <span class="font-semibold text-gray-700">{{ $kitchenLabel }}</span>
                    </p>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
