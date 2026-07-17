<div>
    @if($showModal && !empty($order))
        <div wire:key="delete-order-modal-root" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl border border-[#EBE3D3] overflow-hidden">
                <div class="p-6 text-center space-y-4">
                    <div style="background-color: #fef2f2; border-color: #fecaca;" class="w-14 h-14 mx-auto rounded-full border flex items-center justify-center">
                        <svg style="color: #dc2626;" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>

                    <div class="space-y-2">
                        <p class="text-sm font-semibold text-[#5D4037] leading-relaxed">
                            Any prepaid amount will be credited back to your Middo Balance. Unpaid COD orders are cancelled with no wallet credit.
                        </p>
                        <p class="text-lg font-black text-[#2B1A11]">Are you sure?</p>
                    </div>

                    @if($errorMessage !== '')
                        <p class="text-sm font-semibold text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2">{{ $errorMessage }}</p>
                    @endif

                    @if(!empty($order['menu_item']['name']))
                        <p class="text-xs font-bold text-[#635347]">
                            {{ $order['menu_item']['name'] }} · ৳{{ number_format($order['total_amount'], 0) }}
                        </p>
                    @endif

                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeModal"
                                class="flex-1 py-3 rounded-xl border border-[#EBE3D3] text-sm font-bold text-[#635347] bg-[#F7F4EB] hover:bg-[#EFE9DC] transition">
                            Cancel
                        </button>
                        <button type="button" wire:click="confirmDelete"
                                style="background-color: #dc2626; color: #ffffff;"
                                class="flex-1 py-3 rounded-xl bg-middo-danger hover:bg-middo-danger-dark text-[#FFFFFF] text-sm font-black transition shadow-sm hover:opacity-90">
                            Delete Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
