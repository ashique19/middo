<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-middo-dark">Rider money</h1>
        <p class="text-sm text-gray-500 mt-1">
            Approve rider payment requests (cash till or bank float) when Due is cleared.
            Middo cash: <span class="font-bold text-middo-dark">৳{{ number_format($middoCash) }}</span>
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="space-y-4">
        @forelse($withdrawals as $w)
            @php
                $preview = $previews[$w->id] ?? null;
                $channel = (string) ($w->payout_channel ?: \App\Support\PayoutChannel::CASH);
                $needsBank = \App\Support\PayoutChannel::usesBankFloat($channel);
            @endphp
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-mono text-xs text-gray-400">#{{ $w->id }}</p>
                        <p class="font-bold text-middo-dark">{{ $w->rider?->name }}</p>
                        <p class="text-xs text-gray-500">{{ $w->rider?->mobile }}</p>
                        <p class="mt-2 text-sm">
                            <span class="font-semibold">{{ $w->payoutChannelLabel() }}</span>
                            <span class="text-gray-500">· {{ $w->payoutDetailsSummary() }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-black text-middo-dark">৳{{ number_format($w->amount) }}</p>
                        <p class="text-xs text-gray-500">{{ $w->notes ?: 'No notes' }}</p>
                    </div>
                </div>
                @if($preview)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                        <div class="rounded-xl bg-gray-50 px-3 py-2"><span class="text-gray-400 font-bold uppercase">Wallet</span><div class="font-mono font-bold">৳{{ number_format($preview['wallet']) }}</div></div>
                        <div @class(['rounded-xl px-3 py-2', 'bg-rose-50' => $preview['blocked_by_due'], 'bg-gray-50' => ! $preview['blocked_by_due']])>
                            <span class="text-gray-400 font-bold uppercase">Due float</span>
                            <div class="font-mono font-bold">৳{{ number_format($preview['due_float']) }}</div>
                            @if($preview['blocked_by_due'])
                                <div class="text-rose-700 font-semibold mt-1">Clear handovers first</div>
                            @endif
                        </div>
                        <div class="rounded-xl bg-gray-50 px-3 py-2"><span class="text-gray-400 font-bold uppercase">Open payables</span><div class="font-mono font-bold">৳{{ number_format($preview['open_payables_total']) }}</div></div>
                        <div class="rounded-xl bg-gray-50 px-3 py-2"><span class="text-gray-400 font-bold uppercase">FIFO fit</span><div class="font-mono font-bold">৳{{ number_format($preview['fifo_fit_total']) }}</div></div>
                    </div>
                    @if(count($preview['open_payables']) > 0)
                        <p class="text-[11px] text-gray-500">
                            Open:
                            {{ collect($preview['open_payables'])->map(fn ($p) => '#'.$p['id'].' ৳'.$p['amount'].($p['order_id'] ? ' (order '.$p['order_id'].')' : ''))->implode(' · ') }}
                            @if($preview['open_payables_total'] > collect($preview['open_payables'])->sum('amount'))
                                · …
                            @endif
                        </p>
                    @endif
                @endif
                @if($canWriteMoney)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-gray-50">
                        @if($needsBank)
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Pay from bank</label>
                                <select wire:model="approveBankAccountId.{{ $w->id }}" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    <option value="">Select account…</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->label() }}</option>
                                    @endforeach
                                </select>
                                @error('approveBankAccountId.'.$w->id) <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Review notes</label>
                            <input type="text" wire:model="approveReviewNotes.{{ $w->id }}" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Optional">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Proof (optional)</label>
                            <input type="file" wire:model="approveAttachment.{{ $w->id }}" accept="image/*,.pdf" class="block w-full text-xs">
                            <div wire:loading wire:target="approveAttachment.{{ $w->id }}" class="text-xs text-gray-500 mt-1">Uploading…</div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="approveWithdrawal({{ $w->id }})"
                                wire:confirm="Approve rider withdrawal #{{ $w->id }}?"
                                @disabled($preview['blocked_by_due'] ?? false)
                                class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold disabled:opacity-50">Approve</button>
                        <button type="button" wire:click="rejectWithdrawal({{ $w->id }})"
                                class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">Reject</button>
                    </div>
                @else
                    <span class="text-xs font-semibold text-gray-400">Awaiting accounts</span>
                @endif
            </div>
        @empty
            <div class="bg-white border border-gray-100 rounded-2xl p-10 text-center text-gray-400 italic">No pending rider withdrawals.</div>
        @endforelse
    </div>
    @if($withdrawals->hasPages()) <div class="p-3">{{ $withdrawals->links() }}</div> @endif
</div>
