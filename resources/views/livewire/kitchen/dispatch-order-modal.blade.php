<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl my-8">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Dispatch order</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ $orderLabel }}</p>
                        <p class="text-xs font-semibold text-gray-400 mt-1">
                            Select {{ $requiredQuantity }} {{ str('box')->plural($requiredQuantity) }}
                            ({{ count($selectedBoxIds) }} selected)
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
                </div>

                @if($errorMessage)
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                        {{ $errorMessage }}
                    </div>
                @endif

                <div class="max-h-72 overflow-y-auto space-y-2 mb-5">
                    @forelse($availableBoxes as $box)
                        @php $selected = in_array($box['id'], $selectedBoxIds, true); @endphp
                        <button
                            type="button"
                            wire:click="toggleBox({{ $box['id'] }})"
                            wire:key="dispatch-box-{{ $box['id'] }}"
                            @class([
                                'w-full flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition',
                                'border-middo-orange bg-orange-50/50' => $selected,
                                'border-gray-200 hover:border-middo-orange' => ! $selected,
                            ])>
                            <span class="font-mono font-bold text-middo-dark">{{ $box['qr_code_id'] }}</span>
                            <span class="text-xs font-bold {{ $selected ? 'text-middo-orange' : 'text-gray-400' }}">
                                {{ $selected ? 'Selected' : 'Select' }}
                            </span>
                        </button>
                    @empty
                        <p class="text-sm text-gray-400 italic text-center py-8">
                            No boxes available at your kitchen. Accept incoming boxes first.
                        </p>
                    @endforelse
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-3">
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="dispatchOrder"
                        wire:loading.attr="disabled"
                        @disabled(count($availableBoxes) < $requiredQuantity)
                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition disabled:opacity-60">
                        <span wire:loading.remove wire:target="dispatchOrder">Confirm dispatch</span>
                        <span wire:loading wire:target="dispatchOrder">Dispatching...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
