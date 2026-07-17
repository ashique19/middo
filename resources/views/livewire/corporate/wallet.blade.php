<div class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] p-4 md:p-8">
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight">Middo Balance</h1>
                <p class="text-sm font-semibold text-[#635347] mt-1">Top up via the payment gateway and track every credit and debit.</p>
            </div>
            <button type="button" @click="$dispatch('open-wallet-top-up-modal')"
                    class="inline-flex items-center justify-center bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider px-5 py-3 rounded-xl shadow-sm transition">
                Add Money
            </button>
        </div>

        <div class="bg-[#1E4630] text-white rounded-2xl p-6 border border-[#143021] shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-200/70">Available balance</div>
            <div class="text-4xl font-black mt-2 font-mono">৳{{ number_format(auth()->user()->balance ?? 0, 2) }}</div>
        </div>

        <div class="bg-white border border-[#EBE3D3] rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[#EBE3D3]">
                <h2 class="text-lg font-black">Transaction history</h2>
            </div>
            <div class="divide-y divide-[#F0EAE0]">
                @forelse($this->transactions as $tx)
                    <div class="px-5 py-4 flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm font-black text-[#2B1A11]">{{ $tx->description ?: ucfirst($tx->type) }}</div>
                            <div class="text-[11px] font-semibold text-[#A69988] mt-0.5">
                                {{ $tx->created_at?->timezone('Asia/Dhaka')->format('M d, Y g:i A') }} · {{ $tx->type }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-black font-mono {{ in_array($tx->type, ['topup', 'refund'], true) ? 'text-[#1E4630]' : 'text-[#8A441B]' }}">
                                {{ in_array($tx->type, ['topup', 'refund'], true) ? '+' : '−' }}৳{{ number_format($tx->amount) }}
                            </div>
                            <div class="text-[10px] font-bold text-gray-400 font-mono">Bal ৳{{ number_format($tx->balance_after) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center space-y-3">
                        <p class="text-sm font-semibold text-gray-400">No wallet activity yet.</p>
                        <button type="button" @click="$dispatch('open-wallet-top-up-modal')" class="text-xs font-black text-middo-orange hover:underline">
                            Top up Middo Balance →
                        </button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
