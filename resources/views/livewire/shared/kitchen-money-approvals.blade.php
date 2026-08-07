<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-middo-dark">Kitchen money</h1>
        <p class="text-sm text-gray-500 mt-1">
            Approve kitchen withdrawals, settlement batches, and confirm transfers into Middo cash.
            Middo cash: <span class="font-bold text-middo-dark">৳{{ number_format($middoCash) }}</span>
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="$set('tab', 'withdrawals')"
                @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === 'withdrawals', 'bg-white border-gray-200' => $tab !== 'withdrawals'])>
            Pending withdrawals
        </button>
        <button type="button" wire:click="$set('tab', 'batches')"
                @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === 'batches', 'bg-white border-gray-200' => $tab !== 'batches'])>
            Settlement batches
        </button>
        <button type="button" wire:click="$set('tab', 'transfers')"
                @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === 'transfers', 'bg-white border-gray-200' => $tab !== 'transfers'])>
            Pending transfers
        </button>
    </div>

    @if($tab === 'withdrawals')
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
                            <p class="font-bold text-middo-dark">{{ $w->kitchen?->name }}</p>
                            <p class="text-xs text-gray-500">{{ $w->kitchen?->mobile }}</p>
                            <p class="mt-2 text-sm">
                                <span class="font-semibold">{{ $w->payoutChannelLabel() }}</span>
                                <span class="text-gray-500">· {{ $w->payoutDetailsSummary() }}</span>
                            </p>
                            @if($w->notes)
                                <p class="text-xs text-gray-500 mt-1">{{ $w->notes }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-black text-middo-dark">৳{{ number_format($w->amount) }}</p>
                        </div>
                    </div>
                    @if($preview)
                        <div class="text-[11px] text-gray-500 space-y-0.5">
                            <div>Wallet ৳{{ number_format($preview['wallet']) }} · Open payables ৳{{ number_format($preview['open_payables_total']) }}</div>
                            <div>FIFO fit ৳{{ number_format($preview['fifo_fit_total']) }}
                                @unless($preview['fifo_ok'])
                                    <span class="text-amber-700 font-semibold">— amount may not match whole payables</span>
                                @endunless
                            </div>
                        </div>
                    @endif
                    @if($w->status === 'pending' && $canWriteMoney)
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
                                    wire:confirm="Approve withdrawal #{{ $w->id }}?"
                                    class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Approve</button>
                            <button type="button" wire:click="rejectWithdrawal({{ $w->id }})"
                                    class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">Reject</button>
                        </div>
                    @elseif($w->status === 'pending')
                        <span class="text-xs font-semibold text-gray-400">Awaiting accounts</span>
                    @else
                        <span class="capitalize text-xs font-bold">{{ $w->status }}</span>
                    @endif
                </div>
            @empty
                <div class="bg-white border border-gray-100 rounded-2xl p-10 text-center text-gray-400 italic">No pending withdrawals.</div>
            @endforelse
            @if($withdrawals->hasPages()) <div class="p-3">{{ $withdrawals->links() }}</div> @endif
        </div>
    @endif

    @if($tab === 'batches')
        @if($canWriteMoney)
            <form wire:submit="createBatch" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
                <h2 class="text-lg font-bold text-middo-dark">Create settlement batch</h2>
                <p class="text-sm text-gray-500">Group open kitchen payables into one remittance packet. Single withdrawals stay on the Withdrawals tab.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Kitchen</label>
                        <select wire:model.live="batchKitchenId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">Select kitchen…</option>
                            @foreach($kitchens as $kitchen)
                                <option value="{{ $kitchen->id }}">{{ $kitchen->name }} · {{ $kitchen->mobile }}</option>
                            @endforeach
                        </select>
                        @error('batchKitchenId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Batch name</label>
                        <input type="text" wire:model="batchName" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="e.g. Week 32 remittance">
                        @error('batchName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                @if($batchKitchenId)
                    <p class="text-xs text-gray-500">Kitchen wallet ৳{{ number_format($kitchenWallet) }} · Available open payables below (excludes reserved).</p>
                    <div class="max-h-48 overflow-y-auto border border-gray-100 rounded-xl divide-y">
                        @forelse($availablePayables as $p)
                            <label class="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" wire:model="batchPayableIds" value="{{ $p->id }}" class="rounded border-gray-300">
                                <span class="font-mono text-xs text-gray-400">#{{ $p->id }}</span>
                                <span>Order #{{ $p->order_id }}</span>
                                <span class="ml-auto font-bold">৳{{ number_format($p->amount) }}</span>
                            </label>
                        @empty
                            <p class="p-4 text-center text-gray-400 italic text-sm">No available open payables.</p>
                        @endforelse
                    </div>
                    @error('batchPayableIds') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                @endif
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Payout channel</label>
                    <select wire:model.live="batchPayoutChannel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        @foreach(\App\Support\PayoutChannel::all() as $channel)
                            <option value="{{ $channel }}">{{ \App\Support\PayoutChannel::label($channel) }}</option>
                        @endforeach
                    </select>
                </div>
                @if($batchPayoutChannel === \App\Support\PayoutChannel::BANK)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input type="text" wire:model="batchPayoutBankName" placeholder="Bank name" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <input type="text" wire:model="batchPayoutAccountName" placeholder="Account name" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <input type="text" wire:model="batchPayoutAccountNumber" placeholder="Account number" class="sm:col-span-2 rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        @error('batchPayoutAccountNumber') <p class="text-red-500 text-xs sm:col-span-2">{{ $message }}</p> @enderror
                    </div>
                @elseif(in_array($batchPayoutChannel, [\App\Support\PayoutChannel::BKASH, \App\Support\PayoutChannel::NAGAD], true))
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input type="text" wire:model="batchPayoutAccountName" placeholder="Account name" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <input type="text" wire:model="batchPayoutMobile" placeholder="Mobile" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        @error('batchPayoutMobile') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Notes</label>
                    <textarea wire:model="batchNotes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
                </div>
                <button type="submit"
                        @disabled(! $batchKitchenId || count($batchPayableIds) < 1)
                        class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                    Create batch
                </button>
            </form>
        @endif

        <div class="space-y-4">
            @forelse($batches as $b)
                @php
                    $channel = (string) ($b->payout_channel ?: \App\Support\PayoutChannel::CASH);
                    $needsBank = \App\Support\PayoutChannel::usesBankFloat($channel);
                @endphp
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4 space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-gray-400">#{{ $b->id }} · {{ $b->items->count() }} payables</p>
                            <p class="font-bold text-middo-dark">{{ $b->name }}</p>
                            <p class="text-sm text-gray-600">{{ $b->kitchen?->name }} · {{ $b->kitchen?->mobile }}</p>
                            <p class="mt-1 text-sm">
                                <span class="font-semibold">{{ $b->payoutChannelLabel() }}</span>
                                <span class="text-gray-500">· {{ $b->payoutDetailsSummary() }}</span>
                            </p>
                        </div>
                        <p class="text-2xl font-black text-middo-dark">৳{{ number_format($b->amount) }}</p>
                    </div>
                    @if($b->status === 'pending' && $canWriteMoney)
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-gray-50">
                            @if($needsBank)
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Pay from bank</label>
                                    <select wire:model="approveBankAccountId.{{ $b->id }}" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                        <option value="">Select account…</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}">{{ $bank->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('approveBankAccountId.'.$b->id) <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Review notes</label>
                                <input type="text" wire:model="approveReviewNotes.{{ $b->id }}" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Optional">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">Proof (optional)</label>
                                <input type="file" wire:model="approveAttachment.{{ $b->id }}" accept="image/*,.pdf" class="block w-full text-xs">
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="approveBatch({{ $b->id }})"
                                    wire:confirm="Pay settlement batch #{{ $b->id }}?"
                                    class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Approve &amp; pay</button>
                            <button type="button" wire:click="rejectBatch({{ $b->id }})"
                                    class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">Reject</button>
                        </div>
                    @elseif($b->status === 'pending')
                        <span class="text-xs font-semibold text-gray-400">Awaiting accounts</span>
                    @endif
                </div>
            @empty
                <div class="bg-white border border-gray-100 rounded-2xl p-10 text-center text-gray-400 italic">No pending settlement batches.</div>
            @endforelse
            @if($batches->hasPages()) <div class="p-3">{{ $batches->links() }}</div> @endif
        </div>
    @endif

    @if($tab === 'transfers')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-sm min-w-[800px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Transfer</th>
                        <th class="p-3 text-left">Kitchen</th>
                        <th class="p-3 text-right">Amount</th>
                        <th class="p-3 text-left">Reference</th>
                        <th class="p-3 text-left">Proof</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($transfers as $t)
                        <tr>
                            <td class="p-3 font-mono">#{{ $t->id }}</td>
                            <td class="p-3">
                                <div class="font-semibold">{{ $t->kitchen?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $t->kitchen?->mobile }}</div>
                            </td>
                            <td class="p-3 text-right font-bold">৳{{ number_format($t->amount) }}</td>
                            <td class="p-3 text-gray-600">{{ $t->reference_code ?: '—' }}</td>
                            <td class="p-3">
                                @if($t->proof_path)
                                    <a href="{{ asset($t->proof_path) }}" target="_blank" class="text-middo-orange font-semibold hover:underline">View</a>
                                @else —
                                @endif
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                @if($t->status === 'pending' && $canWriteMoney)
                                    <button type="button" wire:click="confirmTransfer({{ $t->id }})"
                                            wire:confirm="Confirm transfer #{{ $t->id }} into Middo cash?"
                                            class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Confirm</button>
                                    <button type="button" wire:click="rejectTransfer({{ $t->id }})"
                                            class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">Reject</button>
                                @elseif($t->status === 'pending')
                                    <span class="text-xs font-semibold text-gray-400">Awaiting accounts</span>
                                @else
                                    <span class="capitalize text-xs font-bold">{{ $t->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No pending transfers.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($transfers->hasPages()) <div class="p-3">{{ $transfers->links() }}</div> @endif
        </div>
    @endif
</div>
