<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Kitchen account</h1>
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
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Quick actions</p>
            <div class="flex flex-wrap gap-2 mt-2">
                <button type="button" wire:click="$set('tab', 'withdraw')" class="px-3 py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold">Request withdrawal</button>
                <button type="button" wire:click="$set('tab', 'send')" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark">Send money to Middo</button>
                <a href="{{ route('kitchen.cash-handovers') }}" class="px-3 py-1.5 rounded-xl border border-sky-200 text-sky-800 text-xs font-bold bg-sky-50">Cash handovers →</a>
            </div>
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach(['statement' => 'Statement', 'withdraw' => 'Withdraw', 'withdrawals' => 'My withdrawals', 'send' => 'Send to Middo', 'transfers' => 'My transfers'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === $key, 'bg-white text-gray-700 border-gray-200' => $tab !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($tab === 'statement')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b text-sm font-bold text-middo-dark">Kitchen wallet ledger</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[640px]">
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
                            <tr>
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
            @if($statement->hasPages()) <div class="p-3">{{ $statement->links() }}</div> @endif
        </div>

        @if($openPayables->isNotEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <h2 class="text-sm font-bold text-middo-dark mb-2">Open payables (FIFO for withdrawals)</h2>
                <ul class="text-sm text-gray-600 space-y-1">
                    @php $running = 0; @endphp
                    @foreach($openPayables as $p)
                        @php $running += $p->amount; @endphp
                        <li class="flex justify-between gap-3">
                            <span>Order #{{ $p->order_id }} · ৳{{ number_format($p->amount) }}</span>
                            <span class="font-mono text-xs text-gray-400">prefix ৳{{ number_format($running) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    @if($tab === 'withdraw')
        <form wire:submit="requestWithdrawal" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Request withdrawal</h2>
            <p class="text-sm text-gray-500">Withdraw when Middo owes you (positive balance). Amount must match a FIFO total of whole open payables. Choose Bank / bKash / Nagad / Cash — Middo pays on approval.</p>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Amount (৳)</label>
                <input type="number" min="1" wire:model="withdrawAmount" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                @error('withdrawAmount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @include('livewire.partials.payout-channel-fields')
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Notes</label>
                <textarea wire:model="withdrawNotes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Submit request</button>
        </form>
    @endif

    @if($tab === 'withdrawals')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-sm min-w-[640px]">
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
                        <tr>
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
            @if($withdrawals->hasPages()) <div class="p-3">{{ $withdrawals->links() }}</div> @endif
        </div>
    @endif

    @if($tab === 'send')
        <form wire:submit="submitTransfer" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
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
            <button type="submit" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Submit transfer</button>
        </form>
    @endif

    @if($tab === 'transfers')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-sm min-w-[640px]">
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
                        <tr>
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
            @if($transfers->hasPages()) <div class="p-3">{{ $transfers->links() }}</div> @endif
        </div>
    @endif
</div>
