<div>
    @if($showModal && !empty($order))
        <div wire:key="track-order-modal-root" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-[#EBE3D3] overflow-hidden max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between p-5 border-b border-[#EBE3D3] shrink-0">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-[#2B1A11]">Track Order</h2>
                        <p class="text-xs font-semibold text-[#635347] mt-0.5">
                            {{ $order['menu_item']['name'] ?? 'Custom Selection' }}
                            · {{ \Carbon\Carbon::parse($order['delivery_date'])->format('M d, Y') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 rounded-lg text-gray-400 hover:text-[#2B1A11] hover:bg-[#F7F4EB] transition" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 overflow-y-auto flex-1">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-dashed border-[#EBE3D3]">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Current Status</p>
                            <p class="text-sm font-black text-[#2B1A11] mt-0.5 capitalize">{{ str_replace('_', ' ', $order['order_status'] ?? 'pending') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Order Total</p>
                            <p class="text-sm font-black font-mono text-middo-orange mt-0.5">৳{{ number_format($order['total_amount'], 0) }}</p>
                        </div>
                    </div>

                    @if(count($logs) > 0)
                        <div class="space-y-0">
                            @foreach($logs as $index => $log)
                                <div class="relative flex gap-3 {{ $index < count($logs) - 1 ? 'pb-5' : '' }}">
                                    @if($index < count($logs) - 1)
                                        <div class="absolute left-[11px] top-6 bottom-0 w-px bg-[#EBE3D3]"></div>
                                    @endif

                                    <div class="relative z-10 shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center {{ $index === 0 ? 'bg-[#1E4630] border-[#1E4630]' : 'bg-white border-[#DDD3BE]' }}">
                                        @if($index === 0)
                                            <div class="w-2 h-2 rounded-full bg-white"></div>
                                        @endif
                                    </div>

                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-sm font-black text-[#2B1A11] leading-tight">
                                                {{ $this->logLabel($log['event']) }}
                                            </p>
                                            <time class="text-[10px] font-bold text-gray-400 whitespace-nowrap shrink-0">
                                                {{ \Carbon\Carbon::parse($log['created_at'])->timezone('Asia/Dhaka')->format('M d · g:i A') }}
                                            </time>
                                        </div>
                                        <p class="text-xs font-semibold text-[#635347] mt-1 leading-relaxed">
                                            {{ $this->logDescription($log) }}
                                        </p>
                                        @if(!empty($log['performer_name']))
                                            <p class="text-[10px] font-bold text-gray-400 mt-1">
                                                by {{ $log['performer_name'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="bg-[#F9F6F0] border border-[#EBE3D3] rounded-xl p-6 text-center">
                            <p class="text-sm font-semibold text-[#635347]">No activity recorded for this order yet.</p>
                        </div>
                    @endif
                </div>

                <div class="p-5 border-t border-[#EBE3D3] shrink-0">
                    <button type="button" wire:click="closeModal"
                            class="w-full py-3 rounded-xl bg-[#1E4630] hover:bg-[#143021] text-white text-sm font-black transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
