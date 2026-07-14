<div>
    @if($showModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl my-8">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">{{ $canManage ? 'Attach Meal Items' : 'Attached Meal Items' }}</h2>
                        <p class="text-sm text-gray-500 mt-1">Menu: <span class="font-semibold text-middo-dark">{{ $menuName }}</span></p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                @if($canManage)
                    <input wire:model.live.debounce.250ms="search" type="text" placeholder="Search meal items..."
                        class="w-full mb-4 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                @endif

                <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-xl divide-y divide-gray-100">
                    @forelse($mealItems as $meal)
                        @if($canManage)
                            <label wire:key="attach-meal-{{ $meal['id'] }}"
                                class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox"
                                    @checked(in_array($meal['id'], $selectedMealItemIds, true))
                                    wire:click="toggleMeal({{ $meal['id'] }})"
                                    class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                                <span class="flex-1 text-sm font-medium text-gray-800">{{ $meal['name'] }}</span>
                                <span class="text-xs font-semibold text-gray-500">৳{{ number_format($meal['total_cost']) }}</span>
                            </label>
                        @else
                            <div wire:key="view-meal-{{ $meal['id'] }}" class="px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-gray-800">{{ $meal['name'] }}</span>
                                    <span class="text-xs font-semibold text-gray-500 whitespace-nowrap">৳{{ number_format($meal['total_cost']) }}</span>
                                </div>
                                @if(!empty($meal['summary']))
                                    <p class="text-xs text-gray-400 mt-1">{{ $meal['summary'] }}</p>
                                @endif
                            </div>
                        @endif
                    @empty
                        <p class="p-6 text-center text-sm text-gray-400 italic">
                            {{ $canManage ? 'No meal items found.' : 'No meal items attached to this menu.' }}
                        </p>
                    @endforelse
                </div>

                <div class="flex justify-end gap-3 pt-5">
                    <button type="button" wire:click="closeModal" class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50">
                        {{ $canManage ? 'Cancel' : 'Close' }}
                    </button>
                    @if($canManage)
                        <button type="button" wire:click="save" class="px-4 py-2.5 rounded-xl bg-middo-orange text-white text-sm font-bold hover:opacity-90">
                            Save Attachments
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
