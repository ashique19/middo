<div>
    @if($showModal)
        <div wire:key="wallet-top-up-modal-root" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl border border-[#EBE3D3] max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-[#EBE3D3] sticky top-0 bg-white rounded-t-2xl">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-[#2B1A11]">Add Money</h2>
                        <p class="text-xs font-semibold text-[#635347] mt-0.5">
                            Current balance:
                            <span class="font-mono text-[#1E4630]">৳{{ number_format(auth()->user()->balance ?? 0, 2) }}</span>
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 rounded-lg text-gray-400 hover:text-[#2B1A11] hover:bg-[#F7F4EB] transition" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="topUp" class="p-5 space-y-4">
                    @if($successMessage)
                        <div class="bg-[#1E4630] text-white text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm">
                            {{ $successMessage }}
                        </div>
                    @endif

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Amount (৳)</label>
                        <input type="number" wire:model="amount" min="100" max="500000" step="1"
                               class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                        @error('amount') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-gray-400 font-medium mt-1.5">Minimum ৳100. Matches the corporate mobile wallet top-up.</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach([500, 1000, 2000, 5000] as $preset)
                            <button type="button" wire:click="$set('amount', '{{ $preset }}')"
                                    class="text-[11px] font-black px-3 py-1.5 rounded-xl border border-[#EBE3D3] bg-[#F7F4EB] hover:bg-[#EFE9DC] text-[#2B1A11] transition">
                                ৳{{ number_format($preset) }}
                            </button>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-dashed border-gray-100">
                        <button type="button" wire:click="closeModal"
                                class="text-xs font-black text-[#635347] hover:text-[#2B1A11] px-4 py-2.5 rounded-xl transition">
                            Close
                        </button>
                        <button type="submit"
                                class="bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider px-6 py-2.5 rounded-xl shadow-sm transition-colors">
                            <span wire:loading.remove wire:target="topUp">Top Up</span>
                            <span wire:loading wire:target="topUp">Processing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
