<div>
    @if($showModal)
        <div wire:key="middo-boxes-custody-modal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl border border-[#EBE3D3] max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-[#EBE3D3] sticky top-0 bg-white rounded-t-2xl">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-[#2B1A11]">Middo Boxes with you</h2>
                        <p class="text-xs font-semibold text-[#635347] mt-0.5">{{ count($boxes) }} box(es) at your office</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 rounded-lg text-gray-400 hover:text-[#2B1A11] hover:bg-[#F7F4EB] transition" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <p class="text-sm text-[#635347] font-medium leading-relaxed">
                        You don’t ship boxes back yourself. A Middo rider collects empty boxes on the next delivery or pickup run — keep them accessible near reception.
                    </p>

                    @forelse($boxes as $box)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-[#EBE3D3] bg-[#F7F4EB] px-3 py-2.5">
                            <div>
                                <div class="text-sm font-black text-[#2B1A11] font-mono">{{ $box['qr_code_id'] }}</div>
                                <div class="text-[11px] font-semibold text-[#A69988]">{{ str($box['box_model_type'])->headline() }}</div>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-[#8A441B] bg-amber-100 px-2 py-1 rounded-full">At office</span>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-[#EBE3D3] px-4 py-8 text-center text-sm font-semibold text-gray-400">
                            No Middo Boxes currently at your office.
                        </div>
                    @endforelse

                    <a href="{{ route('contact') }}" class="block text-center text-xs font-black text-middo-orange hover:underline pt-1">
                        Need help with a box? Contact support
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
