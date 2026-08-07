<div class="max-w-5xl mx-auto py-6 sm:py-8 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Kitchen account</h1>
        <p class="text-sm text-gray-500">Dispatch credits your wallet (Middo owes you). Cash from riders debits it. Surplus cash means you owe Middo.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            @if($balance > 0)
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Middo owes you</p>
                <p class="text-3xl font-black text-middo-dark">৳{{ number_format($balance) }}</p>
            @elseif($balance < 0)
                <p class="text-xs font-bold uppercase tracking-wider text-rose-600 mb-1">You owe Middo</p>
                <p class="text-3xl font-black text-rose-700">৳{{ number_format(abs($balance)) }}</p>
            @else
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Wallet balance</p>
                <p class="text-3xl font-black text-middo-dark">৳0</p>
                <p class="text-xs text-gray-500 mt-1">Settled</p>
            @endif
            @if($openPayableTotal > 0)
                <p class="text-xs text-gray-500 mt-1">Open dispatch payables ৳{{ number_format($openPayableTotal) }}</p>
            @endif
        </div>
        @if($balance > 0 || $balance < 0 || $pendingCashHandovers > 0)
            <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Quick actions</p>
                <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2 mt-2">
                    @if($balance > 0)
                        <button type="button" wire:click="openWithdrawForm" class="w-full sm:w-auto px-3 py-2.5 sm:py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold">Request withdrawal</button>
                    @endif
                    @if($balance < 0)
                        <button type="button" wire:click="openSendForm" class="w-full sm:w-auto px-3 py-2.5 sm:py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark">Send money to Middo</button>
                    @endif
                    @if($pendingCashHandovers > 0)
                        <a href="{{ route('kitchen.cash-handovers') }}" class="w-full sm:w-auto inline-flex justify-center px-3 py-2.5 sm:py-1.5 rounded-xl border border-sky-200 text-sky-800 text-xs font-bold bg-sky-50">
                            Cash handovers →
                            <span class="ml-1 opacity-80">({{ $pendingCashHandovers }})</span>
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    @php
        $accountTabs = ['statement' => 'Statement'];
        if ($balance > 0) {
            $accountTabs['withdraw'] = 'Withdraw';
        }
        $accountTabs['withdrawals'] = 'My withdrawals';
        if ($balance < 0) {
            $accountTabs['send'] = 'Send to Middo';
        }
        $accountTabs['transfers'] = 'My transfers';
    @endphp
    <div class="flex flex-wrap gap-2">
        @foreach($accountTabs as $key => $label)
            <button type="button"
                    wire:click="{{ $key === 'withdraw' ? 'openWithdrawForm' : ($key === 'send' ? 'openSendForm' : "\$set('tab', '{$key}')") }}"
                    @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === $key, 'bg-white text-gray-700 border-gray-200' => $tab !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($tab === 'statement')
        <div class="space-y-3">
            <div class="px-1 text-sm font-bold text-middo-dark">Kitchen wallet ledger</div>

            <div class="md:hidden space-y-3">
                @forelse($statement as $row)
                    <div wire:key="stmt-m-{{ $row->id }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800">{{ str($row->entry_type)->replace('_', ' ')->headline() }}</p>
                                <p class="text-xs text-gray-500">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                            </div>
                            <p @class(['shrink-0 font-bold tabular-nums', 'text-emerald-700' => $row->amount > 0, 'text-rose-700' => $row->amount < 0])>
                                {{ $row->amount > 0 ? '+' : '' }}৳{{ number_format($row->amount) }}
                            </p>
                        </div>
                        @if($row->description)
                            <p class="text-sm text-gray-600">{{ $row->description }}</p>
                        @endif
                        <p @class(['text-xs font-mono', 'text-rose-700' => $row->balance_after < 0, 'text-gray-500' => $row->balance_after >= 0])>
                            Balance ৳{{ number_format($row->balance_after) }}
                        </p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-gray-400 italic text-sm">
                        No ledger entries yet. Balance credits when you dispatch an order.
                    </div>
                @endforelse
            </div>

            <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[560px]">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                            <tr>
                                <th class="p-3 text-left">When</th>
                                <th class="p-3 text-left">Type</th>
                                <th class="p-3 text-left">Description</th>
                                <th class="p-3 text-right">Amount</th>
                                <th class="p-3 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($statement as $row)
                                <tr wire:key="stmt-{{ $row->id }}">
                                    <td class="p-3 text-gray-500 whitespace-nowrap">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                                    <td class="p-3 font-semibold">{{ str($row->entry_type)->replace('_', ' ')->headline() }}</td>
                                    <td class="p-3 text-gray-600">{{ $row->description ?: '—' }}</td>
                                    <td @class(['p-3 text-right font-bold', 'text-emerald-700' => $row->amount > 0, 'text-rose-700' => $row->amount < 0])>
                                        {{ $row->amount > 0 ? '+' : '' }}৳{{ number_format($row->amount) }}
                                    </td>
                                    <td @class(['p-3 text-right font-mono', 'text-rose-700' => $row->balance_after < 0])>
                                        ৳{{ number_format($row->balance_after) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">No ledger entries yet. Balance credits when you dispatch an order.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($statement->hasPages()) <div class="overflow-x-auto">{{ $statement->links() }}</div> @endif
        </div>

        @if($openPayables->isNotEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <h2 class="text-sm font-bold text-middo-dark mb-2">Open payables (settled on full withdrawal)</h2>
                <ul class="text-sm text-gray-600 space-y-1">
                    @php $running = 0; @endphp
                    @foreach($openPayables as $p)
                        @php $running += $p->amount; @endphp
                        <li class="flex justify-between gap-3">
                            <span class="min-w-0 break-words">Order #{{ $p->order_id }} · ৳{{ number_format($p->amount) }}</span>
                            <span class="font-mono text-xs text-gray-400 shrink-0">prefix ৳{{ number_format($running) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    @if($tab === 'withdraw')
        <form id="account-withdraw-panel" wire:submit="requestWithdrawal" class="scroll-mt-24 bg-white border border-gray-100 rounded-2xl shadow-sm p-4 sm:p-5 space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Request withdrawal</h2>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Amount (৳) — full receivable</label>
                <input type="text" readonly value="{{ $withdrawAmount !== null ? number_format((int) $withdrawAmount) : '—' }}"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-middo-dark tabular-nums">
            </div>
            @include('livewire.partials.payout-channel-fields')
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Notes</label>
                <textarea wire:model="withdrawNotes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
            </div>
            <button type="submit" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Submit request</button>
        </form>
    @endif

    @if($tab === 'withdrawals')
        <div class="md:hidden space-y-3">
            @forelse($withdrawals as $w)
                <div wire:key="wd-m-{{ $w->id }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-1">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-mono font-semibold">#{{ $w->id }}</p>
                        <p class="font-bold tabular-nums">৳{{ number_format($w->amount) }}</p>
                    </div>
                    <p class="text-sm font-semibold text-gray-800">{{ $w->payoutChannelLabel() }}</p>
                    <p class="text-xs text-gray-500">{{ $w->payoutDetailsSummary() }}</p>
                    <p class="text-xs capitalize text-gray-600">{{ $w->status }} · {{ $w->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                </div>
            @empty
                <div class="rounded-2xl border border-gray-100 bg-white p-8 text-center text-gray-400 italic text-sm">No withdrawal requests yet.</div>
            @endforelse
        </div>
        <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[560px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-3 text-left">ID</th>
                            <th class="p-3 text-right">Amount</th>
                            <th class="p-3 text-left">Channel</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($withdrawals as $w)
                            <tr wire:key="wd-{{ $w->id }}">
                                <td class="p-3 font-mono">#{{ $w->id }}</td>
                                <td class="p-3 text-right font-bold">৳{{ number_format($w->amount) }}</td>
                                <td class="p-3">
                                    <div class="font-semibold">{{ $w->payoutChannelLabel() }}</div>
                                    <div class="text-xs text-gray-500">{{ $w->payoutDetailsSummary() }}</div>
                                </td>
                                <td class="p-3 capitalize">{{ $w->status }}</td>
                                <td class="p-3 text-gray-500">{{ $w->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No withdrawal requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($withdrawals->hasPages()) <div class="overflow-x-auto">{{ $withdrawals->links() }}</div> @endif
    @endif

    @if($tab === 'send')
        <form id="account-send-panel" wire:submit="submitTransfer" class="scroll-mt-24 bg-white border border-gray-100 rounded-2xl shadow-sm p-4 sm:p-5 space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Send money to Middo</h2>
            <p class="text-sm text-gray-500">When you hold surplus cash (you owe Middo), transfer it with proof. Ops confirmation credits your wallet and Middo’s cash ledger.</p>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Amount (৳)</label>
                <input type="number" min="1" wire:model="transferAmount" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                @error('transferAmount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Reference / Txn ID</label>
                <input type="text" wire:model="transferReference" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Proof image</label>
                <input type="file" wire:model="transferProof" accept="image/*" class="block w-full text-sm">
                @error('transferProof') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                <div wire:loading wire:target="transferProof" class="text-xs text-gray-500 mt-1">Uploading…</div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Notes</label>
                <textarea wire:model="transferNotes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
            </div>
            <button type="submit" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Submit transfer</button>
        </form>
    @endif

    @if($tab === 'transfers')
        <div class="md:hidden space-y-3">
            @forelse($transfers as $t)
                <div wire:key="xfer-m-{{ $t->id }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-1">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-mono font-semibold">#{{ $t->id }}</p>
                        <p class="font-bold tabular-nums">৳{{ number_format($t->amount) }}</p>
                    </div>
                    <p class="text-xs capitalize text-gray-600">{{ $t->status }} · {{ $t->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                    @if($t->proof_path)
                        <a href="{{ asset($t->proof_path) }}" target="_blank" class="text-xs font-semibold text-middo-orange hover:underline">View proof</a>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-gray-100 bg-white p-8 text-center text-gray-400 italic text-sm">No transfers yet.</div>
            @endforelse
        </div>
        <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[560px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-3 text-left">ID</th>
                            <th class="p-3 text-right">Amount</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">Proof</th>
                            <th class="p-3 text-left">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($transfers as $t)
                            <tr wire:key="xfer-{{ $t->id }}">
                                <td class="p-3 font-mono">#{{ $t->id }}</td>
                                <td class="p-3 text-right font-bold">৳{{ number_format($t->amount) }}</td>
                                <td class="p-3 capitalize">{{ $t->status }}</td>
                                <td class="p-3">
                                    @if($t->proof_path)
                                        <a href="{{ asset($t->proof_path) }}" target="_blank" class="text-middo-orange font-semibold hover:underline">View</a>
                                    @else —
                                    @endif
                                </td>
                                <td class="p-3 text-gray-500">{{ $t->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No transfers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($transfers->hasPages()) <div class="overflow-x-auto">{{ $transfers->links() }}</div> @endif
    @endif
</div>
