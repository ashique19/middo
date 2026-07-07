<div>
    @if($showModal && !empty($order))
        <div wire:key="edit-order-modal-root" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl border border-[#EBE3D3] overflow-hidden">
                <div class="flex items-center justify-between p-5 border-b border-[#EBE3D3]">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-[#2B1A11]">Edit Order</h2>
                        <p class="text-xs font-semibold text-[#635347] mt-0.5">Update quantity for this scheduled lunch.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 rounded-lg text-gray-400 hover:text-[#2B1A11] hover:bg-[#F7F4EB] transition" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-5">
                    <div class="rounded-2xl overflow-hidden bg-[#F9F6F0] border border-[#EBE3D3]">
                        @if(!empty($order['menu_item']['thumbnail']))
                            <img src="{{ asset($order['menu_item']['thumbnail']) }}"
                                 alt="{{ $order['menu_item']['name'] ?? 'Meal' }}"
                                 class="w-full h-44 object-cover">
                        @else
                            <div class="w-full h-44 bg-[#ECE7DA] flex items-center justify-center text-sm font-semibold text-[#635347]">No image</div>
                        @endif
                        <div class="p-4">
                            <h3 class="text-base font-black text-[#2B1A11]">{{ $order['menu_item']['name'] ?? 'Custom Selection' }}</h3>
                            <p class="text-xs font-semibold text-[#635347] mt-1">
                                {{ \Carbon\Carbon::parse($order['delivery_date'])->format('M d, Y') }} · {{ $order['delivery_time'] }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-2">Quantity</label>
                        <div class="flex items-center justify-center gap-4">
                            <button type="button" wire:click="decrementQuantity"
                                    class="w-10 h-10 rounded-xl bg-[#F7F4EB] border border-[#EBE3D3] text-[#2B1A11] font-black text-lg hover:bg-[#EFE9DC] transition disabled:opacity-40"
                                    @disabled($quantity <= 1)>−</button>
                            <span class="text-2xl font-black font-mono text-[#2B1A11] w-12 text-center">{{ $quantity }}</span>
                            <button type="button" wire:click="incrementQuantity"
                                    class="w-10 h-10 rounded-xl bg-[#F7F4EB] border border-[#EBE3D3] text-[#2B1A11] font-black text-lg hover:bg-[#EFE9DC] transition disabled:opacity-40"
                                    @disabled($quantity >= $this->maxQuantity)>+</button>
                        </div>
                        @error('quantity') <span class="text-red-500 text-xs font-semibold mt-1 block text-center">{{ $message }}</span> @enderror
                    </div>

                    <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-4">
                        <p class="text-sm font-semibold text-[#5D4037] leading-relaxed">
                            To modify any other detail, please delete this order and order again. Balance will be added to your account upon deleting the order.
                        </p>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button type="button" wire:click="closeModal"
                                class="flex-1 py-3 rounded-xl border border-[#EBE3D3] text-sm font-bold text-[#635347] bg-[#F7F4EB] hover:bg-[#EFE9DC] transition">
                            Cancel
                        </button>
                        <button type="button" wire:click="save"
                                class="flex-1 py-3 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-black transition">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
