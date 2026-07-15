<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Send Boxes to Kitchen</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Assigning <span class="font-semibold text-middo-dark">{{ count($boxIds) }}</span>
                            {{ str('box')->plural(count($boxIds)) }} from warehouse inventory.
                        </p>
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
                            Destination kitchen
                        </label>
                        <select
                            id="kitchen-select"
                            wire:model="selectedKitchenId"
                            class="w-full border border-gray-300 rounded-xl shadow-sm focus:border-middo-orange focus:ring-middo-orange p-3 text-sm">
                            <option value="">Select a kitchen</option>
                            @foreach($kitchens as $kitchen)
                                <option value="{{ $kitchen['id'] }}">{{ $kitchen['name'] }}</option>
                            @endforeach
                        </select>
                        @error('selectedKitchenId')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="rider-select" class="block text-sm font-semibold text-gray-700 mb-2">
                            Rider
                        </label>
                        <select
                            id="rider-select"
                            wire:model="selectedRiderId"
                            class="w-full border border-gray-300 rounded-xl shadow-sm focus:border-middo-orange focus:ring-middo-orange p-3 text-sm">
                            <option value="">Select a rider</option>
                            @foreach($riders as $rider)
                                <option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>
                            @endforeach
                        </select>
                        @error('selectedRiderId')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    @if($kitchens === [] || $riders === [])
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                            @if($kitchens === [] && $riders === [])
                                No active kitchens or delivery riders found.
                            @elseif($kitchens === [])
                                No active kitchens found. Activate a kitchen user first.
                            @else
                                No delivery riders found. Add a user with the delivery role first.
                            @endif
                        </p>
                    @endif

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
                            @disabled($kitchens === [] || $riders === [])
                            class="px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Send to kitchen</span>
                            <span wire:loading wire:target="save">Sending...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
